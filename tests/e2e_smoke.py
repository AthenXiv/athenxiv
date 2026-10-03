"""Athenaeum end-to-end smoke test.

Exercises the real HTTP surface of a running instance:
  register -> submit a PDF -> OpenTimestamps proof created -> admin review ->
  public paper page -> PDF streaming (with range requests) -> proof download.

Usage:
  bin\\serve.cmd 8123            (in one terminal, from the project root)
  python tests/e2e_smoke.py http://127.0.0.1:8123 admin@… password

Only the standard library is used on purpose: it must run anywhere PHP does.
"""

from __future__ import annotations

import http.cookiejar
import io
import json
import os
import re
import shlex
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid

BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8123"
ADMIN_EMAIL = sys.argv[2] if len(sys.argv) > 2 else "admin@athenaeum.test"
ADMIN_PASSWORD = sys.argv[3] if len(sys.argv) > 3 else "athenaeum-admin-2026"

PASSED: list[str] = []
FAILED: list[str] = []


def check(label: str, condition: bool, detail: str = "") -> bool:
    if condition:
        PASSED.append(label)
        print(f"  [ok]   {label}")
    else:
        FAILED.append(f"{label} — {detail}")
        print(f"  [FAIL] {label}" + (f" — {detail}" if detail else ""))
    return condition


class Browser:
    """Minimal cookie-aware HTTP client."""

    def __init__(self, base: str) -> None:
        self.base = base.rstrip("/")
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.jar),
            NoRedirect(),
        )

    def request(self, method: str, path: str, data=None, headers=None, follow=True):
        url = path if path.startswith("http") else self.base + path
        request = urllib.request.Request(url, data=data, method=method)
        request.add_header("User-Agent", "AthenXivSmokeTest/1.0")
        # Deterministic UI language: these suites assert on English labels, while
        # a Chinese-facing deployment sets ui.default_locale to zh-CN. Callers
        # that test negotiation pass their own Accept-Language and win.
        request.add_header("Accept-Language", "en")
        for key, value in (headers or {}).items():
            request.add_header(key, value)
        try:
            response = self.opener.open(request, timeout=60)
            status, body, response_headers = response.status, response.read(), dict(response.headers)
        except urllib.error.HTTPError as error:
            status, body, response_headers = error.code, error.read(), dict(error.headers)

        if follow and status in (301, 302, 303, 307, 308) and "Location" in response_headers:
            location = response_headers["Location"]
            path_only = urllib.parse.urlparse(location).path or "/"
            return self.request("GET", path_only, None, None, follow=True)
        return status, body, response_headers

    def get(self, path: str, **kwargs):
        return self.request("GET", path, **kwargs)

    def post(self, path: str, fields: dict, files: dict | None = None, **kwargs):
        # Extra headers (Accept: application/json, …) merge with the defaults.
        extra = dict(kwargs.pop("headers", {}) or {})
        if files:
            body, content_type = encode_multipart(fields, files)
            headers = {"Content-Type": content_type}
            headers.update(extra)
            return self.request("POST", path, body, headers, **kwargs)
        body = urllib.parse.urlencode(fields, doseq=True).encode()
        headers = {"Content-Type": "application/x-www-form-urlencoded"}
        headers.update(extra)
        return self.request("POST", path, body, headers, **kwargs)

    def token(self, path: str) -> str:
        status, body, _ = self.get(path, follow=True)
        html = body.decode("utf-8", "replace")
        match = re.search(r'name="_token"\s+value="([^"]+)"', html)
        if not match:
            raise SystemExit(f"no CSRF token found on {path} (HTTP {status})")
        return match.group(1)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):  # noqa: D102
        return None


def encode_multipart(fields: dict, files: dict) -> tuple[bytes, str]:
    boundary = "----AthenaeumBoundary" + uuid.uuid4().hex
    buffer = io.BytesIO()
    for name, value in fields.items():
        values = value if isinstance(value, (list, tuple)) else [value]
        for item in values:
            buffer.write(f"--{boundary}\r\n".encode())
            buffer.write(f'Content-Disposition: form-data; name="{name}"\r\n\r\n'.encode())
            buffer.write(str(item).encode())
            buffer.write(b"\r\n")
    for name, (filename, content, content_type) in files.items():
        buffer.write(f"--{boundary}\r\n".encode())
        buffer.write(
            f'Content-Disposition: form-data; name="{name}"; filename="{filename}"\r\n'.encode()
        )
        buffer.write(f"Content-Type: {content_type}\r\n\r\n".encode())
        buffer.write(content)
        buffer.write(b"\r\n")
    buffer.write(f"--{boundary}--\r\n".encode())
    return buffer.getvalue(), f"multipart/form-data; boundary={boundary}"


def make_pdf(title: str) -> bytes:
    """A small but structurally valid single page PDF."""
    text = f"BT /F1 14 Tf 40 760 Td ({title}) Tj ET"
    objects = [
        b"<< /Type /Catalog /Pages 2 0 R >>",
        b"<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        b"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] "
        b"/Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>",
        None,  # content stream, filled below
        b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
    ]
    stream = text.encode("latin-1", "replace")
    objects[3] = (
        b"<< /Length " + str(len(stream)).encode() + b" >>\nstream\n" + stream + b"\nendstream"
    )

    out = io.BytesIO()
    out.write(b"%PDF-1.4\n")
    offsets = [0]
    for index, body in enumerate(objects, start=1):
        offsets.append(out.tell())
        out.write(f"{index} 0 obj\n".encode())
        out.write(body)
        out.write(b"\nendobj\n")
    xref_offset = out.tell()
    out.write(f"xref\n0 {len(objects) + 1}\n".encode())
    out.write(b"0000000000 65535 f \n")
    for offset in offsets[1:]:
        out.write(f"{offset:010d} 00000 n \n".encode())
    out.write(
        f"trailer\n<< /Size {len(objects) + 1} /Root 1 0 R >>\nstartxref\n{xref_offset}\n%%EOF\n".encode()
    )
    return out.getvalue()


def main() -> int:
    stamp = uuid.uuid4().hex[:8]
    user_email = f"author-{stamp}@example.org"
    user_password = "philosophy-password-2026"
    nickname = f"作者{stamp}"

    print(f"AthenXiv end-to-end smoke test against {BASE}\n")

    # Registration normally demands an e-mailed code (see e2e_v2.py, which tests
    # that flow against a local SMTP fixture). This script has no mail server, so
    # it switches the requirement off for the run and restores it afterwards.
    toggle = os.path.join(os.path.dirname(os.path.abspath(__file__)), "fixtures", "set_setting.php")
    project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    configured = os.environ.get("PHP_CMD")
    php_candidates = [shlex.split(configured)] if configured else [
        ["php"],
        ["php", "-d", "extension=pdo_sqlite", "-d", "extension=sqlite3"],
    ]

    def run_toggle(value: str):
        """Set the requirement; returns the helper's stdout or None."""
        for prefix in php_candidates:
            try:
                result = subprocess.run(
                    prefix + [toggle, "registration.verify_email", value],
                    capture_output=True, text=True, timeout=60, cwd=project_root,
                )
            except Exception:  # noqa: BLE001
                continue
            if result.returncode == 0:
                return result.stdout
        return None

    def set_requirement(value: str) -> bool:
        return run_toggle(value) is not None

    # Remember what it was, so a run that starts from a deliberate setting puts
    # it back exactly as it found it.
    requirement_before = "1"
    probe = run_toggle("1")
    if probe is not None and "registration.verify_email: false" in probe:
        requirement_before = "0"
    if not set_requirement("0"):
        print("  (could not switch the registration code requirement off; "
              "set PHP_CMD if php lacks pdo_sqlite)")

    # ---------------------------------------------------------------- public
    print("public pages:")
    browser = Browser(BASE)
    for path, needle in [
        ("/", "hero"),
        ("/papers", "container"),
        ("/login", "_token"),
        ("/register", "_token"),
        ("/about", "prose-page"),
        ("/about/timestamping", "opentimestamps.org"),
        ("/guidelines", "prose-page"),
        ("/robots.txt", "Sitemap:"),
        ("/sitemap.xml", "<urlset"),
    ]:
        status, body, _ = browser.get(path)
        check(f"GET {path} → 200", status == 200, f"HTTP {status}")
        check(f"GET {path} contains '{needle}'", needle.lower() in body.decode("utf-8", "replace").lower())

    status, body, _ = browser.get("/definitely-not-a-page")
    check("unknown path returns 404", status == 404, f"HTTP {status}")

    status, body, _ = browser.get("/api/stats")
    stats = json.loads(body.decode())
    check("GET /api/stats is JSON", stats.get("ok") is True)

    # ------------------------------------------------------------- register
    print("\nregistration:")
    author = Browser(BASE)
    token = author.token("/register")
    status, body, _ = author.post("/register", {
        "_token": token,
        "nickname": nickname,
        "email": user_email,
        "affiliation": "Independent",
        "password": user_password,
        "password_confirmation": user_password,
        "terms": "1",
    })
    check("POST /register accepted", status in (200, 302), f"HTTP {status}")
    status, body, _ = author.get("/dashboard")
    html = body.decode("utf-8", "replace")
    check("dashboard reachable after registration", status == 200 and "dashboard" in html.lower(), f"HTTP {status}")

    # --------------------------------------------------------- submit paper
    print("\npaper submission (this creates a real OpenTimestamps proof):")
    title = f"On the Priority of Philosophical Claims {stamp}"
    abstract = (
        "This submission exists to exercise the Athenaeum pipeline end to end: "
        "metadata, PDF storage, hashing, OpenTimestamps submission to the public "
        "calendars and the moderation workflow. It argues, briefly, that a proof "
        "of existence is not a proof of quality, and that conflating the two is a "
        "category mistake with practical consequences for open archives."
    )
    token = author.token("/submit")
    pdf = make_pdf(title)
    status, body, _ = author.post("/submit", {
        "_token": token,
        "title": title,
        "subtitle": "A smoke test with a philosophical excuse",
        "abstract": abstract,
        "language": "en",
        "keywords": "timestamping, open access, epistemology",
        "license": "CC BY 4.0",
        "visibility": "public",
        "author_name[]": [nickname, "Second Author"],
        "author_affiliation[]": ["Independent", "University of Nowhere"],
        "author_email[]": [user_email, ""],
        "author_orcid[]": ["", ""],
        "link_label[]": ["Preprint"],
        "link_url[]": ["https://example.org/preprint"],
        "link_kind[]": ["other"],
    }, files={"pdf": ("paper.pdf", pdf, "application/pdf")})
    check("POST /submit accepted", status in (200, 302), f"HTTP {status}")

    status, body, _ = author.get("/dashboard/papers")
    html = body.decode("utf-8", "replace")
    check("paper appears in the author dashboard", title in html, "title not found")
    uid_match = re.search(r"\b(ATH-[A-Z0-9]{6})\b", html)
    if not uid_match:
        print("\ncannot continue without a paper id")
        return 1
    paper_uid = uid_match.group(1)
    print(f"  paper id: {paper_uid}")

    # check the OpenTimestamps row through the dashboard table
    check(
        "OpenTimestamps state shown for the new paper",
        "OpenTimestamps" in html or "ots-chip" in html,
    )

    # ------------------------------------------------------------ author page
    print("\nauthor view of the paper:")
    status, body, _ = author.get(f"/paper/{paper_uid}")
    page = body.decode("utf-8", "replace")
    check(f"GET /paper/{paper_uid} → 200", status == 200, f"HTTP {status}")
    check("title rendered", title in page)
    check("abstract rendered", abstract[:60] in page)
    check("second author rendered", "Second Author" in page)
    check("external link rendered", "example.org/preprint" in page)
    check("OpenTimestamps block rendered", "OpenTimestamps" in page)
    check("verify link points at opentimestamps.org", "opentimestamps.org" in page)
    check(
        "verify link carries an explanatory tooltip",
        "drop the proof" in page.lower() or "verify" in page.lower(),
    )
    check("SHA-256 of the PDF displayed", re.search(r"[0-9a-f]{64}", page) is not None)
    check("download button rendered", f"/paper/{paper_uid}/download" in page)

    # --------------------------------------------------------- file handling
    print("\nfile streaming:")
    status, body, headers = author.get(f"/paper/{paper_uid}/file")
    check("inline PDF served", status == 200 and headers.get("Content-Type") == "application/pdf",
          f"HTTP {status} {headers.get('Content-Type')}")
    check("PDF bytes match the upload", body == pdf, f"{len(body)} vs {len(pdf)} bytes")
    check("range support advertised", headers.get("Accept-Ranges") == "bytes")

    status, body, headers = author.get(
        f"/paper/{paper_uid}/file", headers={"Range": "bytes=0-99"}
    )
    check("range request honoured (206)", status == 206, f"HTTP {status}")
    check("range slice is 100 bytes", len(body) == 100, f"{len(body)} bytes")
    check("Content-Range header present", "Content-Range" in headers, str(headers))

    status, body, headers = author.get(f"/paper/{paper_uid}/download")
    check("download endpoint serves the same bytes", body == pdf)
    check("download forces attachment", "attachment" in headers.get("Content-Disposition", ""))

    status, body, _ = author.get(f"/paper/{paper_uid}/cite?format=bibtex")
    check("BibTeX export works", status == 200 and b"@misc" in body)
    status, body, _ = author.get(f"/paper/{paper_uid}/cite?format=ris")
    check("RIS export works", b"TY  - GEN" in body)

    # --------------------------------------------------------------- admin
    print("\nadministration:")
    blocked = Browser(BASE)
    status, body, _ = blocked.get("/admin")
    check("anonymous /admin ends on the login form", b'name="_token"' in body and b"password" in body)
    status, body, _ = blocked.get("/admin/papers")
    check("anonymous /admin/papers lands on the login form", b"password" in body.lower())

    admin = Browser(BASE)
    token = admin.token("/login")
    status, body, _ = admin.post("/login", {"_token": token, "email": ADMIN_EMAIL, "password": ADMIN_PASSWORD})
    status, body, _ = admin.get("/admin")
    dash = body.decode("utf-8", "replace")
    check("admin dashboard reachable", status == 200 and "Administration" in dash, f"HTTP {status}")
    check("review queue listed", paper_uid in dash or "queue" in dash.lower())

    status, body, _ = admin.get("/admin/papers?status=pending")
    listing = body.decode("utf-8", "replace")
    check("pending paper listed for moderation", paper_uid in listing)

    id_match = re.search(r"/admin/paper/(\d+)", listing)
    if not id_match:
        print("\ncannot continue without the internal paper id")
        return 1
    paper_id = id_match.group(1)

    status, body, _ = admin.get(f"/admin/paper/{paper_id}")
    review = body.decode("utf-8", "replace")
    check("moderation screen renders", status == 200 and "moderation" in review.lower())
    check("moderation screen shows the timestamp record", "OpenTimestamps" in review and ".ots" in review)

    token = admin.token(f"/admin/paper/{paper_id}")
    status, _, _ = admin.post(f"/admin/paper/{paper_id}/approve", {
        "_token": token,
        "section_id": "",
        "note": "Approved by the smoke test.",
    })
    check("approve accepted", status in (200, 302), f"HTTP {status}")

    status, body, _ = admin.get(f"/admin/paper/{paper_id}")
    check("paper is now published", "published" in body.decode("utf-8", "replace").lower())

    # timestamps screen
    status, body, _ = admin.get("/admin/timestamps")
    stamps = body.decode("utf-8", "replace")
    check("timestamp admin screen renders", status == 200 and paper_uid in stamps)

    proof_id = re.search(r"/admin/timestamps/(\d+)/proof", stamps)
    if proof_id:
        status, body, headers = admin.get(f"/admin/timestamps/{proof_id.group(1)}/proof")
        check("proof download works", status == 200 and body[:31].startswith(b"\x00OpenTimestamps"),
              f"HTTP {status}, first bytes {body[:12]!r}")
        check("proof download is an attachment", "attachment" in headers.get("Content-Disposition", ""))
    else:
        check("proof download link present", False, "no proof link on the timestamps screen")

    token = admin.token("/admin/timestamps")
    status, body, _ = admin.post("/admin/timestamps/upgrade", {"_token": token, "limit": "5"})
    check("upgrade cycle runs without error", status in (200, 302), f"HTTP {status}")

    # ------------------------------------------------- public visibility
    print("\npublic visibility after approval:")
    anonymous = Browser(BASE)
    status, body, _ = anonymous.get(f"/paper/{paper_uid}")
    public_page = body.decode("utf-8", "replace")
    check("published paper is public", status == 200 and title in public_page, f"HTTP {status}")
    status, body, _ = anonymous.get("/papers")
    check("paper listed in the archive", title in body.decode("utf-8", "replace"))
    status, body, _ = anonymous.get("/sitemap.xml")
    check("paper present in the sitemap", paper_uid in body.decode("utf-8", "replace"))
    status, body, _ = anonymous.get(f"/u/{re.search(r'U[A-Z0-9]{8}', public_page).group(0)}")
    check("public author profile renders", status == 200, f"HTTP {status}")

    # ------------------------------------------------------------- withdraw
    print("\nauthor withdrawal:")
    token = author.token("/dashboard/papers")
    status, _, _ = author.post(f"/paper/{paper_uid}/withdraw", {"_token": token, "reason": "smoke test"})
    check("withdraw accepted", status in (200, 302), f"HTTP {status}")
    status, body, _ = anonymous.get(f"/paper/{paper_uid}")
    check("withdrawn paper hidden from the public", status == 404, f"HTTP {status}")

    # Put the registration requirement back the way we found it.
    if set_requirement(requirement_before):
        check("the registration requirement was restored", True)

    print(f"\n{len(PASSED)} checks passed, {len(FAILED)} failed")
    for failure in FAILED:
        print("  FAILED: " + failure)
    return 1 if FAILED else 0


if __name__ == "__main__":
    sys.exit(main())
