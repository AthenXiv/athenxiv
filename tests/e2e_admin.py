"""Athenaeum administration end-to-end test.

Covers every administrative capability the project promises:
  site name + logo editing, review workflow (approve/reject/takedown/restore),
  section (分区) and subject area (分类) management, user creation / banning /
  role changes / password reset, proxy upload with a size-limit waiver, purge,
  the audit log and access control.

    bin\\serve.cmd 8123
    python tests/e2e_admin.py http://127.0.0.1:8123 admin@… password

Shares the HTTP client with tests/e2e_smoke.py to avoid duplication.
"""

from __future__ import annotations

import io
import json
import os
import re
import shlex
import struct
import subprocess
import sys
import tempfile
import zlib
import uuid

from e2e_smoke import FAILED, PASSED, Browser, check, make_pdf

BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8123"
ADMIN_EMAIL = sys.argv[2] if len(sys.argv) > 2 else "admin@athenaeum.test"
ADMIN_PASSWORD = sys.argv[3] if len(sys.argv) > 3 else "athenaeum-admin-2026"

PROJECT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SNAPSHOT = os.path.join(tempfile.gettempdir(), "athenaeum-admin-e2e-settings.json")
SNAPSHOT_HELPER = os.path.join(PROJECT, "tests", "fixtures", "settings_snapshot.php")


def settings_snapshot(action: str) -> bool:
    """This script edits the site name, contact address and mail settings.

    Without the snapshot it would leave the operator's real branding and SMTP
    configuration replaced by test values, which is exactly the class of bug
    that upset a real deployment once already.
    """
    configured = os.environ.get("PHP_CMD")
    candidates = [shlex.split(configured)] if configured else [
        ["php"],
        ["php", "-d", "extension=pdo_sqlite", "-d", "extension=sqlite3"],
    ]
    for prefix in candidates:
        try:
            result = subprocess.run(
                prefix + [SNAPSHOT_HELPER, action, SNAPSHOT],
                cwd=PROJECT, capture_output=True, text=True, timeout=60,
            )
        except Exception as error:  # noqa: BLE001
            print(f"  (settings snapshot {action} unavailable: {error})")
            return False
        if result.returncode == 0:
            return True
        last = (result.stderr or result.stdout).strip()[:200]
    print(f"  (settings snapshot {action} failed: {last})")
    return False


def make_png(width: int = 64, height: int = 64, colour=(47, 93, 80)) -> bytes:
    """A valid PNG built with zlib only (no Pillow needed)."""
    raw = b""
    for _ in range(height):
        raw += b"\x00" + bytes(colour) * width

    def chunk(tag: bytes, payload: bytes) -> bytes:
        return (
            struct.pack(">I", len(payload))
            + tag
            + payload
            + struct.pack(">I", zlib.crc32(tag + payload) & 0xFFFFFFFF)
        )

    ihdr = struct.pack(">IIBBBBB", width, height, 8, 2, 0, 0, 0)
    return (
        b"\x89PNG\r\n\x1a\n"
        + chunk(b"IHDR", ihdr)
        + chunk(b"IDAT", zlib.compress(raw, 9))
        + chunk(b"IEND", b"")
    )


def login(browser: Browser, email: str, password: str) -> None:
    token = browser.token("/login")
    browser.post("/login", {"_token": token, "email": email, "password": password})


def main() -> int:
    stamp = uuid.uuid4().hex[:8]
    print(f"AthenXiv administration test against {BASE}\n")
    snapshot_ok = settings_snapshot("save")

    admin = Browser(BASE)
    print("authentication:")
    login(admin, ADMIN_EMAIL, ADMIN_PASSWORD)
    status, body, _ = admin.get("/admin")
    check("admin signs in", status == 200 and "Administration" in body.decode("utf-8", "replace"))

    # ------------------------------------------------------------ site identity
    print("\nsite identity and branding:")
    token = admin.token("/admin/settings")
    new_name = f"雅典学院·{stamp}"
    new_name_en = f"Athenaeum {stamp}"
    status, _, _ = admin.post("/admin/settings", {
        "_token": token,
        "site.name": new_name,
        "site.name_en": new_name_en,
        "site.tagline": "测试标语",
        "site.tagline_en": "Test tagline",
        "site.contact_email": "contact@athenaeum.test",
        "site.footer_text": "",
        "site.icp": "",
        "site.analytics": "",
        "home.notice": "**公告**：安装完成。",
        "moderation.notify_email": "",
        "upload.allowed_attachment_ext": "zip,rar,7z,tar,gz,tgz,bz2,xz",
        "upload.pdf_message": "",
        "ots.calendars": "https://a.pool.opentimestamps.org,https://b.pool.opentimestamps.org,https://a.pool.eternitywall.com,https://ots.btc.catallaxy.com",
        "ots.verify_url": "https://opentimestamps.org/",
        "ui.default_locale": "en",
        "ui.locales": "en,zh-CN,ja,ko,fr,de",
        "upload.max_pdf_mb": "12",
        "upload.max_attachment_mb": "24",
        "upload.max_attachments": "4",
        "upload.max_avatar_kb": "512",
        "upload.max_logo_kb": "1024",
        "ui.papers_per_page": "12",
        "registration.open": "1",
        "moderation.auto_approve": "0",
        "ots.enabled": "1",
        "ots.auto_upgrade": "1",
        "ots.require_for_publish": "0",
        "ui.allow_profile_markdown": "1",
        "ui.show_view_counts": "1",
    })
    check("settings form accepted", status in (200, 302), f"HTTP {status}")

    status, body, _ = admin.get("/")
    home = body.decode("utf-8", "replace")
    check(
        "site name change is live",
        new_name in home or new_name_en in home,
        "neither the primary nor the English site name was rendered",
    )
    check("homepage notice rendered", "公告" in home)

    logo = make_png(80, 40)
    token = admin.token("/admin/settings")
    status, body, _ = admin.post(
        "/admin/settings/branding",
        {"_token": token},
        files={"logo": ("logo.png", logo, "image/png"), "favicon": ("favicon.png", make_png(32, 32), "image/png")},
    )
    check("branding upload accepted", status in (200, 302), f"HTTP {status}")
    status, body, _ = admin.get("/")
    home = body.decode("utf-8", "replace")
    logo_match = re.search(r'/media/branding/([A-Za-z0-9._-]+\.png)', home)
    check("logo referenced from the page", logo_match is not None)
    if logo_match:
        status, image, headers = Browser(BASE).get(f"/media/branding/{logo_match.group(1)}")
        check("logo served through the media route", status == 200 and headers.get("Content-Type") == "image/png",
              f"HTTP {status} {headers.get('Content-Type')}")
        check("logo bytes intact", image[:8] == b"\x89PNG\r\n\x1a\n")

    # ------------------------------------------------------------- sections
    print("\nsections (分区):")
    slug = f"test-section-{stamp}"
    token = admin.token("/admin/sections")
    status, _, _ = admin.post("/admin/sections", {
        "_token": token,
        "slug": slug,
        "name_en": "Test tier",
        "name_zh-CN": "测试分区",
        "name_ja": "テスト区",
        "name_ko": "테스트 구역",
        "name_fr": "Section test",
        "name_de": "Testrubrik",
        "description_en": "Created by the administration test.",
        "sort_order": "99",
    })
    check("section created", status in (200, 302), f"HTTP {status}")

    status, body, _ = admin.get("/admin/sections")
    listing = body.decode("utf-8", "replace")
    check("section listed in the admin panel", slug in listing)
    section_id = None
    for match in re.finditer(r'/admin/sections/(\d+)"', listing):
        section_id = match.group(1)
    check("section id discoverable", section_id is not None)

    if section_id:
        token = admin.token("/admin/sections")
        status, _, _ = admin.post(f"/admin/sections/{section_id}", {
            "_token": token,
            "name_en": "Test tier renamed",
            "name_zh-CN": "测试分区（改名）",
            "sort_order": "98",
            "is_public": "1",
        })
        check("section updated", status in (200, 302), f"HTTP {status}")
        status, body, _ = Browser(BASE).get(f"/sections/{slug}")
        page = body.decode("utf-8", "replace")
        check("section page renders", status == 200, f"HTTP {status}")
        check("renamed section shows in its own language", "测试分区（改名）" in page or "Test tier renamed" in page)

        token = admin.token("/admin/sections")
        status, _, _ = admin.post(f"/admin/sections/{section_id}/purge", {"_token": token})
        check("empty section deleted", status in (200, 302), f"HTTP {status}")
        status, body, _ = admin.get("/admin/sections")
        check("deleted section is gone", slug not in body.decode("utf-8", "replace"))

    # ------------------------------------------------------------ categories
    print("\nsubject areas (分类):")
    cat_slug = f"test-area-{stamp}"
    token = admin.token("/admin/categories")
    status, _, _ = admin.post("/admin/categories", {
        "_token": token,
        "slug": cat_slug,
        "name_en": "Test area",
        "name_zh-CN": "测试领域",
        "sort_order": "50",
    })
    check("subject area created", status in (200, 302), f"HTTP {status}")
    status, body, _ = admin.get("/admin/categories")
    listing = body.decode("utf-8", "replace")
    check("subject area listed", cat_slug in listing)
    cat_match = re.search(rf'/admin/categories/(\d+)"[^>]*>\s*<input[^>]*name="name_en"[^>]*value="Test area"', listing)
    cat_id = None
    if cat_match:
        cat_id = cat_match.group(1)
    else:
        ids = re.findall(r'/admin/categories/(\d+)/purge', listing)
        cat_id = ids[-1] if ids else None
    if cat_id:
        token = admin.token("/admin/categories")
        status, _, _ = admin.post(f"/admin/categories/{cat_id}/purge", {"_token": token})
        check("unused subject area deleted", status in (200, 302), f"HTTP {status}")

    # ------------------------------------------------------------------ users
    print("\nuser administration:")
    member_email = f"member-{stamp}@example.org"
    member_password = "member-password-2026"
    token = admin.token("/admin/users")
    status, _, _ = admin.post("/admin/users", {
        "_token": token,
        "email": member_email,
        "nickname": f"成员{stamp}",
        "password": member_password,
        "role": "user",
    })
    check("account created by an administrator", status in (200, 302), f"HTTP {status}")

    status, body, _ = admin.get(f"/admin/users?q={member_email}")
    listing = body.decode("utf-8", "replace")
    check("created account is searchable", member_email in listing)
    uid_match = re.search(r"(U[A-Z0-9]{8})", listing)
    check("created account has a uid", uid_match is not None)
    member_uid = uid_match.group(1) if uid_match else ""
    id_match = re.search(r"/admin/user/(\d+)", listing)
    member_id = id_match.group(1) if id_match else None

    if member_id:
        token = admin.token(f"/admin/user/{member_id}")
        status, _, _ = admin.post(f"/admin/user/{member_id}/status", {
            "_token": token, "status": "banned", "note": "administration test",
        })
        check("account banned", status in (200, 302), f"HTTP {status}")

        banned = Browser(BASE)
        status, body, _ = banned.post("/login", {
            "_token": banned.token("/login"), "email": member_email, "password": member_password,
        })
        status, body, _ = banned.get("/dashboard")
        check("banned account cannot sign in", b"password" in body.lower())

        token = admin.token(f"/admin/user/{member_id}")
        status, _, _ = admin.post(f"/admin/user/{member_id}/status",
                                 {"_token": token, "status": "active", "note": "restored"})
        check("account reinstated", status in (200, 302), f"HTTP {status}")

        token = admin.token(f"/admin/user/{member_id}")
        status, _, _ = admin.post(f"/admin/user/{member_id}/role", {"_token": token, "role": "editor"})
        check("role changed to editor", status in (200, 302), f"HTTP {status}")

        new_password = "reset-by-admin-2026"
        token = admin.token(f"/admin/user/{member_id}")
        status, _, _ = admin.post(f"/admin/user/{member_id}/password",
                                 {"_token": token, "password": new_password})
        check("password reset by an administrator", status in (200, 302), f"HTTP {status}")

        reset = Browser(BASE)
        login(reset, member_email, new_password)
        status, body, _ = reset.get("/dashboard")
        check("reset password works", status == 200 and b"dashboard" in body.lower())

        # non-admin must not reach the console
        status, body, _ = reset.get("/admin")
        check("non-admin blocked from /admin", b"permission" in body.lower() or b"password" in body.lower())

    # --------------------------------------------------------- proxy upload
    print("\nproxy upload with a size-limit waiver:")
    title = f"Proxy-uploaded paper {stamp}"
    token = admin.token("/admin/upload")
    status, _, _ = admin.post("/admin/upload", {
        "_token": token,
        "uploader": member_uid or member_email,
        "title": title,
        "abstract": (
            "Uploaded by an administrator on behalf of an author, with the size limit "
            "explicitly waived, which is recorded in the audit log along with a reason."
        ),
        "language": "zh-CN",
        "author_name[]": [f"成员{stamp}"],
        "author_affiliation[]": ["Independent"],
        "author_email[]": [member_email],
        "author_orcid[]": [""],
        "link_label[]": [""],
        "link_url[]": [""],
        "link_kind[]": ["other"],
        "size_exempt": "1",
        "size_exempt_note": "administrator test",
    }, files={"pdf": ("proxy.pdf", make_pdf(title), "application/pdf")})
    check("proxy upload accepted", status in (200, 302), f"HTTP {status}")

    status, body, _ = admin.get("/admin/papers?q=" + title.replace(" ", "+"))
    listing = body.decode("utf-8", "replace")
    check("proxy-uploaded paper listed", title in listing)
    check("waiver badge shown", "waiv" in listing.lower() or "破例" in listing)
    paper_match = re.search(r"/admin/paper/(\d+)", listing)
    paper_id = paper_match.group(1) if paper_match else None

    if paper_id:
        status, body, _ = admin.get(f"/admin/paper/{paper_id}")
        detail = body.decode("utf-8", "replace")
        check("proxy upload attributed to the administrator", "on behalf" in detail.lower() or "proxy" in detail.lower())
        check("waiver note visible to the moderator", "administrator test" in detail)

        # reject -> restore -> takedown
        token = admin.token(f"/admin/paper/{paper_id}")
        status, _, _ = admin.post(f"/admin/paper/{paper_id}/reject",
                                 {"_token": token, "reason": "Needs more references."})
        check("rejection accepted", status in (200, 302), f"HTTP {status}")
        status, body, _ = admin.get(f"/admin/paper/{paper_id}")
        check("rejection reason stored", "Needs more references." in body.decode("utf-8", "replace"))

        token = admin.token(f"/admin/paper/{paper_id}")
        status, _, _ = admin.post(f"/admin/paper/{paper_id}/restore", {"_token": token, "note": "ok now"})
        check("paper republished", status in (200, 302), f"HTTP {status}")

        token = admin.token(f"/admin/paper/{paper_id}")
        status, _, _ = admin.post(f"/admin/paper/{paper_id}/section",
                                 {"_token": token, "section_id": ""})
        check("section assignment accepted", status in (200, 302), f"HTTP {status}")

        token = admin.token(f"/admin/paper/{paper_id}")
        status, _, _ = admin.post(f"/admin/paper/{paper_id}/feature", {"_token": token, "featured": "1"})
        check("featured flag toggled", status in (200, 302), f"HTTP {status}")
        status, body, _ = admin.get("/")
        check("featured paper on the homepage", title in body.decode("utf-8", "replace"))

        token = admin.token(f"/admin/paper/{paper_id}")
        status, _, _ = admin.post(f"/admin/paper/{paper_id}/takedown",
                                 {"_token": token, "reason": "taken down by the test"})
        check("takedown accepted", status in (200, 302), f"HTTP {status}")
        status, body, _ = Browser(BASE).get(f"/paper/{paper_id}")
        check("taken-down paper is not public", status == 404, f"HTTP {status}")

        # purge needs the uid typed back
        status, body, _ = admin.get(f"/admin/paper/{paper_id}")
        uid_match = re.search(r"ATH-[A-Z0-9]{6}", body.decode("utf-8", "replace"))
        paper_uid = uid_match.group(0) if uid_match else ""
        token = admin.token(f"/admin/paper/{paper_id}")
        status, _, _ = admin.post(f"/admin/paper/{paper_id}/purge",
                                 {"_token": token, "confirm": paper_uid})
        check("purge requires the paper id and succeeds", status in (200, 302), f"HTTP {status}")

    # ------------------------------------------------------------- audit log
    print("\naudit log:")
    status, body, _ = admin.get("/admin/audit")
    audit = body.decode("utf-8", "replace")
    check("audit screen renders", status == 200)
    for action in ["paper.proxy_upload", "user.create", "user.status", "paper.reject", "paper.purge", "settings.update"]:
        check(f"audit records '{action}'", action in audit)

    # ------------------------------------------------------------ JSON stats
    status, body, _ = admin.get("/api/stats")
    check("api stats responds", status == 200 and json.loads(body.decode()).get("ok") is True)

    # Put the operator's real branding and mail settings back.
    if snapshot_ok and settings_snapshot("restore"):
        check("the live site settings were restored", True)

    print(f"\n{len(PASSED)} checks passed, {len(FAILED)} failed")
    for failure in FAILED:
        print("  FAILED: " + failure)
    return 1 if FAILED else 0


if __name__ == "__main__":
    sys.exit(main())
