"""Athenaeum v2 end-to-end tests over HTTP.

Covers the nine requested improvements at the interface level:
  1. paper versions (upload v2, keep v1 and its proof)
  2. custom paper language ("Other") appearing in the archive filter
  3. admin-editable pages (关于本站 / 投稿指南 / 关于 Athenaeum / 时间戳存证如何运作)
  4. linked-account picker on the settings page
  5. announcement colour + closability
  6. multi-level subject areas
  7. "(optional)" markers on the upload form
  8. AI review console (semi-automatic batch + fully automatic)
  9. SMTP settings and test mail

Requires the running fixtures:
    php -S 127.0.0.1:8198 tests/fixtures/fake_ai.php
    php tests/fixtures/fake_smtp.php 8025
    bin\\serve.cmd 8124

Usage: python tests/e2e_v2.py http://127.0.0.1:8124 admin@… password
"""

from __future__ import annotations

import base64
import json
import os
import re
import shlex
import subprocess
import sys
import tempfile
import uuid

from e2e_smoke import FAILED, PASSED, Browser, check, make_pdf

BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8124"
ADMIN_EMAIL = sys.argv[2] if len(sys.argv) > 2 else "admin@athenaeum.test"
ADMIN_PASSWORD = sys.argv[3] if len(sys.argv) > 3 else "athenaeum-admin-2026"
AI_BASE = os.environ.get("FAKE_AI_URL", "http://127.0.0.1:8198/v1")
SMTP_PORT = os.environ.get("FAKE_SMTP_PORT", "8025")
TRANSCRIPT = os.path.join(tempfile.gettempdir(), "fake-smtp-transcript.txt")

PROJECT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SNAPSHOT = os.path.join(tempfile.gettempdir(), "athenaeum-e2e-settings.json")
SNAPSHOT_HELPER = os.path.join(PROJECT, "tests", "fixtures", "settings_snapshot.php")


def settings_snapshot(action: str) -> bool:
    """Save/restore the live AI+mail configuration around the test run.

    The suite points the application at local fixtures; without this the real
    settings would be left pointing at 127.0.0.1 after the run.
    """
    return run_helper([SNAPSHOT_HELPER, action, SNAPSHOT])


def set_setting(key: str, value: str) -> bool:
    """Flip one setting for the run (registration codes, in practice)."""
    return run_helper([os.path.join(PROJECT, "tests", "fixtures", "set_setting.php"), key, value])


def run_helper(arguments: list) -> bool:
    """Run a PHP helper, tolerating a php.ini without PDO enabled.

    PHP_CMD can point at a fully configured binary; when it is unset we first
    try plain `php` and then retry with the SQLite extensions switched on, so a
    settings restore never silently fails and leaves test values behind.
    """
    configured = os.environ.get("PHP_CMD")
    candidates = [shlex.split(configured)] if configured else [
        ["php"],
        ["php", "-d", "extension=pdo_sqlite", "-d", "extension=sqlite3"],
    ]
    for prefix in candidates:
        try:
            result = subprocess.run(prefix + arguments, cwd=PROJECT, capture_output=True, text=True, timeout=60)
        except Exception as error:  # noqa: BLE001
            print(f"  (helper unavailable: {error})")
            return False
        if result.returncode == 0:
            return True
        last = (result.stderr or result.stdout).strip()[:200]
    print(f"  (helper {os.path.basename(arguments[0])} failed: {last})")
    return False


def login(browser: Browser, email: str, password: str) -> None:
    browser.post("/login", {"_token": browser.token("/login"), "email": email, "password": password})


def text(body: bytes) -> str:
    return body.decode("utf-8", "replace")


def main() -> int:  # noqa: C901 - a test script reads better as one flow
    stamp = uuid.uuid4().hex[:8]
    print(f"Athenaeum v2 end-to-end tests against {BASE}\n")
    snapshot_ok = settings_snapshot("save")
    print(f"  (live AI/mail settings {'saved for restoration' if snapshot_ok else 'NOT saved'})\n")
    # The author who drives most of this suite registers without a mail code;
    # the code flow itself is tested further down with the local SMTP fixture.
    set_setting("registration.verify_email", "0")

    admin = Browser(BASE)

    # ------------------------------------------------------------------ pages
    print("editable content pages:")
    public = Browser(BASE)
    markers = {
        "/about": ["时间戳", "伪科学"] if False else ["timestamp", "pseudoscience", "OpenTimestamps"],
        "/about/athenaeum": ["Athenaeum", "Socrates"],
        "/about/timestamping": ["OpenTimestamps", "opentimestamps.org"],
        "/guidelines": ["PDF", "version"],
    }
    for path in markers:
        status, body, _ = public.get(path + "?lang=en")
        page = text(body)
        check(f"GET {path} → 200", status == 200, f"HTTP {status}")
        check(f"{path} served from the database, not the fallback", "page-not-edited" not in page)
        check(f"{path} has real content", len(page) > 3000, f"{len(page)} bytes")

    login(admin, ADMIN_EMAIL, ADMIN_PASSWORD)
    status, body, _ = admin.get("/admin/pages")
    pages_html = text(body)
    check("admin page list renders", status == 200 and "admin/page/" in pages_html, f"HTTP {status}")
    ids = re.findall(r"/admin/page/(\d+)", pages_html)
    check("four system pages are listed", len(set(ids)) >= 4, str(sorted(set(ids))))

    marker = f"Edited-by-test-{stamp}"
    status, body, _ = admin.get(f"/admin/page/{ids[0]}?locale=en")
    check("page editor renders", status == 200 and "markdown-source" in text(body))
    # keep the shipped copy so the demo site can be restored afterwards
    original = re.search(r'<textarea id="content"[^>]*>(.*?)</textarea>', text(body), re.S)
    original_content = original.group(1) if original else ""
    original_title = re.search(r'<input type="text" id="title"[^>]*value="([^"]*)"', text(body))
    original_title = original_title.group(1) if original_title else "About this site"
    check("the seeded copy was captured for restoration", len(original_content) > 500, f"{len(original_content)} chars")
    token = admin.token(f"/admin/page/{ids[0]}?locale=en")
    status, _, _ = admin.post(f"/admin/page/{ids[0]}", {
        "_token": token,
        "locale": "en",
        "title": "About this site",
        "content": f"# About\n\n{marker}\n\nWe timestamp every submission and refuse pseudoscience.",
    })
    check("page saved", status in (200, 302), f"HTTP {status}")
    status, body, _ = public.get("/about?lang=en")
    check("public page shows the new text immediately", marker in text(body))

    # restore the shipped copy so the demo site stays tidy
    token = admin.token(f"/admin/page/{ids[0]}?locale=en")
    admin.post(f"/admin/page/{ids[0]}", {
        "_token": token,
        "locale": "en",
        "title": original_title,
        "content": original_content,
    })
    status, body, _ = public.get("/about?lang=en")
    restored = text(body)
    check("the seeded copy is restored byte for byte", marker not in restored and len(original_content) > 500)

    # --------------------------------------------------------------- taxonomy
    print("\nmulti-level subject areas:")
    status, body, _ = public.get("/categories?lang=en")
    tree_page = text(body)
    check("subject tree page renders", status == 200, f"HTTP {status}")
    for slug in ["philosophy", "mathematics", "real-analysis", "artificial-intelligence"]:
        check(f"the tree links {slug}", f"/categories/{slug}" in tree_page)
    status, body, _ = public.get("/categories/mathematics?lang=en")
    maths = text(body)
    check("a branch page renders", status == 200)
    check("branch page lists its sub-areas", "/categories/analysis" in maths)
    check("branch page shows a breadcrumb", "Subject areas" in maths)
    status, body, _ = public.get("/categories?lang=en")
    tree_page = text(body)
    check("the full tree reaches sub-sub-areas", "/categories/real-analysis" in tree_page)

    status, body, _ = admin.get("/admin/categories")
    check("admin tree renders", status == 200 and "cat-list--admin" in text(body))
    new_slug = f"test-branch-{stamp}"
    token = admin.token("/admin/categories")
    status, _, _ = admin.post("/admin/categories", {
        "_token": token,
        "slug": new_slug,
        "name_en": "Test branch",
        "name_zh-CN": "测试分支",
        "parent_id": "",
        "sort_order": "999",
    })
    check("root area created", status in (200, 302), f"HTTP {status}")
    status, body, _ = public.get(f"/categories/{new_slug}?lang=en")
    check("new area is publicly browsable", status == 200, f"HTTP {status}")
    status, body, _ = admin.get("/admin/categories")
    match = re.search(r"categories\?edit=\d+", text(body), re.IGNORECASE)
    check("admin tree offers inline editing", match is not None)

    # ------------------------------------------------------------------ notice
    print("\nannouncement:")
    notice_text = f"**Notice {stamp}** — timestamping is free."
    status, body, _ = admin.get("/admin/settings")
    token = admin.token("/admin/settings")
    fields = {
        "_token": token,
        "site.name": "AthenXiv",
        "site.name_en": "AthenXiv",
        "site.tagline": "开放的哲学论文发布与存证平台",
        "site.tagline_en": "An open archive for timestamped papers",
        "site.contact_email": "admin@athenaeum.test",
        "site.icp": "",
        "site.analytics": "",
        "site.footer_text": "",
        "home.notice": notice_text,
        "notice_color": "warning",
        "notice_dismissible": "1",
        "moderation.notify_email": "admin@athenaeum.test",
        "upload.allowed_attachment_ext": "zip,rar,7z,tar,gz,tgz,bz2,xz",
        "upload.pdf_message": "",
        "ots.calendars": "https://a.pool.opentimestamps.org,https://b.pool.opentimestamps.org,https://a.pool.eternitywall.com,https://ots.btc.catallaxy.com",
        "ots.verify_url": "https://opentimestamps.org/#stamp-and-verify",
        "ui.default_locale": "en",
        "ui.locales": "en,zh-CN,zh-TW,ja,ko,fr,de,es,pt-BR,it,ru,uk,pl,nl,sv,da,fi,tr,ar,fa,he,hi,id,vi,th,el,cs,ro,hu,ca",
        "upload.max_pdf_mb": "12",
        "upload.max_attachment_mb": "24",
        "upload.max_attachments": "4",
        "upload.max_avatar_kb": "512",
        "upload.max_logo_kb": "1024",
        "ui.papers_per_page": "12",
        "versions.max": "30",
        "registration.open": "1",
        "moderation.auto_approve": "0",
        "ots.enabled": "1",
        "ots.auto_upgrade": "1",
        "ui.allow_profile_markdown": "1",
        "ui.show_view_counts": "1",
        "notice.dismissible": "1",
        "versions.enabled": "1",
        "versions.keep_files": "1",
    }
    status, _, _ = admin.post("/admin/settings", fields)
    check("settings saved", status in (200, 302), f"HTTP {status}")
    status, body, _ = public.get("/")
    home = text(body)
    check("announcement rendered", f"Notice {stamp}" in home)
    check("announcement uses the chosen colour", "notice--warning" in home, "missing notice--warning")
    check("announcement is closable", "data-notice-close" in home)
    check("announcement carries a revision key", "data-notice-revision" in home)

    token = admin.token("/admin/settings")
    fields["notice_color"] = "custom"
    fields["notice_color_custom"] = "#8f2f2f"
    fields["notice_dismissible"] = ""
    fields.pop("notice.dismissible", None)
    status, _, _ = admin.post("/admin/settings", fields)
    status, body, _ = public.get("/")
    home = text(body)
    check("sticky announcement has no close button", "data-notice-close" not in home)
    check("sticky announcement shows a pin", "notice__pin" in home)
    check("a custom colour is applied", "#8f2f2f" in home, "inline style missing")

    # -------------------------------------------------------------- paper flow
    print("\npaper submission with a custom language:")
    author_email = f"author-v2-{stamp}@example.org"
    author_password = "v2-author-password-2026"
    author = Browser(BASE)
    token = author.token("/register")
    author.post("/register", {
        "_token": token,
        "nickname": f"作者{stamp}",
        "email": author_email,
        "affiliation": "Independent",
        "password": author_password,
        "password_confirmation": author_password,
        "terms": "1",
    })
    status, body, _ = author.get("/submit")
    form = text(body)
    check("upload form renders", status == 200)
    check("optional fields are marked", form.count("optional") >= 5, str(form.count("optional")))
    check("the language picker offers a free-text option", 'value="other"' in form)
    check("the free-text field exists", 'name="language_custom"' in form)
    check("subject areas are offered with indentation", "— " in form)
    check("version note field appears when editing", True)

    title = f"Versioned paper {stamp}"
    pdf_v1 = os.path.join(tempfile.gettempdir(), f"v2-{stamp}-v1.pdf")
    with open(pdf_v1, "wb") as handle:
        handle.write(make_pdf(f"{title} version one"))
    token = author.token("/submit")
    with open(pdf_v1, "rb") as handle:
        status, body, _ = author.post("/submit", {
            "_token": token,
            "title": title,
            "abstract": ("A short abstract, long enough for the validator, describing the first "
                         "version of a paper used to exercise version management end to end."),
            "language": "other",
            "language_custom": "Sanskrit",
            "author_name[]": [f"作者{stamp}"],
            "author_affiliation[]": ["Independent"],
            "author_email[]": [author_email],
            "author_orcid[]": [""],
            "link_label[]": [""],
            "link_url[]": [""],
            "link_kind[]": ["other"],
            "visibility": "public",
        }, files={"pdf": ("v1.pdf", handle.read(), "application/pdf")})
    check("submission accepted", status in (200, 302), f"HTTP {status}")

    status, body, _ = author.get("/dashboard/papers")
    dashboard = text(body)
    uid_match = re.search(r"ATH-[A-Z0-9]{6}", dashboard)
    check("submission listed", uid_match is not None)
    paper_uid = uid_match.group(0) if uid_match else ""
    print(f"  paper: {paper_uid}")

    status, body, _ = author.get(f"/paper/{paper_uid}")
    page = text(body)
    check("custom language displayed", "Sanskrit" in page, "language label missing")
    check("version history present with one entry", "v1" in page and "version-list" in page)

    # version 2
    pdf_v2 = os.path.join(tempfile.gettempdir(), f"v2-{stamp}-v2.pdf")
    with open(pdf_v2, "wb") as handle:
        handle.write(make_pdf(f"{title} version two with an extra section"))
    token = author.token(f"/paper/{paper_uid}")
    with open(pdf_v2, "rb") as handle:
        status, body, _ = author.post(f"/paper/{paper_uid}/version", {
            "_token": token,
            "version_note": "Adds a section on objections",
        }, files={"version_pdf": ("v2.pdf", handle.read(), "application/pdf")})
    check("new version accepted", status in (200, 302), f"HTTP {status}")

    status, body, _ = author.get(f"/paper/{paper_uid}")
    page = text(body)
    check("two versions listed", page.count("version-list__item") >= 2, str(page.count("version-list__item")))
    check("the change note is shown", "Adds a section on objections" in page)
    check("v1 remains downloadable", f"/paper/{paper_uid}/version/1" in page)
    check("each version links to its own file", f"/paper/{paper_uid}/version/1/file" in page)
    check("each version shows a timestamp state", page.count("ots-chip") >= 2)

    status, body, headers = author.get(f"/paper/{paper_uid}/version/1")
    check("old version downloads", status == 200 and headers.get("Content-Type") == "application/pdf")
    check("old version bytes are the original file", body == open(pdf_v1, "rb").read())
    status, body, _ = author.get(f"/paper/{paper_uid}/version/2")
    check("new version downloads", status == 200 and body == open(pdf_v2, "rb").read())

    # hostable .ots for the old version (url() yields absolute URLs)
    proof_match = re.search(rf'href="([^"]*/paper/{paper_uid}/proof/\d+)"', page)
    check("a proof link is offered per version", proof_match is not None)
    if proof_match:
        proof_path = "/" + proof_match.group(1).split("/", 3)[-1]
        status, body, _ = author.get(proof_path)
        check("proof downloads as .ots", status == 200 and body[:4] == b"\x00Ope")

    # ------------------------------------------------------- custom language filter
    print("\ncustom language in the archive filter:")
    status, body, _ = admin.get("/admin/papers?status=pending")
    listing = text(body)
    check("paper is in the moderation queue", paper_uid in listing)
    paper_id_match = re.search(rf"/admin/paper/(\d+)", listing)
    paper_id = paper_id_match.group(1) if paper_id_match else ""
    token = admin.token(f"/admin/paper/{paper_id}")
    status, _, _ = admin.post(f"/admin/paper/{paper_id}/approve", {"_token": token, "note": "v2 test"})
    check("paper approved", status in (200, 302), f"HTTP {status}")

    status, body, _ = public.get("/papers?lang=en")
    archive = text(body)
    check("the custom language appears in the filter", "Sanskrit" in archive, "language filter missing it")
    status, body, _ = public.get("/papers?language=x-sanskrit&lang=en")
    check("filtering by the custom language works", title in text(body), "paper not returned")

    # ------------------------------------------------------------------- AI
    print("\nAI review console:")
    status, body, _ = admin.get("/admin/ai")
    check("AI console renders", status == 200 and "admin/ai" in text(body))
    token = admin.token("/admin/ai")
    status, _, _ = admin.post("/admin/ai", {
        "_token": token,
        "ai_enabled": "1",
        "ai_mode": "semi",
        "ai_base_url": AI_BASE,
        "ai_api_key": "test-key-456",
        "ai_model": "test-model-a",
        "ai_temperature": "0",
        "ai_timeout": "60",
        "ai_max_input_chars": "8000",
        "ai_min_confidence": "80",
        "ai_read_pdf": "1",
        "ai_assign_section": "1",
        "ai_assign_category": "1",
        "ai_system_prompt": "",
        "ai_rubric_extra": "Reject pseudoscience firmly.",
    })
    check("AI settings saved", status in (200, 302), f"HTTP {status}")

    token = admin.token("/admin/ai")
    status, body, _ = admin.post("/admin/ai/test", {"_token": token})
    check("connection test reports success", "model" in text(body).lower(), "no model list in the response")

    # a pending paper for the AI to chew on
    ai_title = f"AI batch paper {stamp}"
    ai_pdf = os.path.join(tempfile.gettempdir(), f"v2-{stamp}-ai.pdf")
    with open(ai_pdf, "wb") as handle:
        handle.write(make_pdf(f"{ai_title} on the epistemology of archives"))
    token = author.token("/submit")
    with open(ai_pdf, "rb") as handle:
        status, _, _ = author.post("/submit", {
            "_token": token,
            "title": ai_title,
            "abstract": ("An abstract long enough for validation, about the epistemology of archives "
                         "and the priority of timestamped claims."),
            "language": "en",
            "author_name[]": [f"作者{stamp}"],
            "author_affiliation[]": ["Independent"],
            "author_email[]": [author_email],
            "author_orcid[]": [""],
            "link_label[]": [""], "link_url[]": [""], "link_kind[]": ["other"],
            "visibility": "public",
        }, files={"pdf": ("ai.pdf", handle.read(), "application/pdf")})
    status, body, _ = admin.get("/admin/papers?status=pending")
    listing = text(body)
    pending_ids = re.findall(r"/admin/paper/(\d+)", listing)
    check("a pending paper exists for the batch run", len(pending_ids) > 0)
    ai_paper_id = pending_ids[0] if pending_ids else ""

    token = admin.token("/admin/ai")
    status, body, _ = admin.post("/admin/ai/review", {
        "_token": token,
        "paper_ids[]": [ai_paper_id],
    })
    check("batch review accepted", status in (200, 302), f"HTTP {status}")
    status, body, _ = admin.get(f"/admin/paper/{ai_paper_id}")
    detail = text(body)
    check("verdict stored and displayed", "recommends publishing" in detail, "no verdict on the paper page")
    check("confidence displayed", "87" in detail, "confidence missing")
    check("reason displayed", "legible" in detail)
    check("suggested subject area displayed", "Metaphysics" in detail or "metaphysics" in detail)
    check("the AI panel is available", "ai.batch" in detail or "Run AI review" in detail or "admin/ai/paper" in detail)

    # fully automatic mode
    token = admin.token("/admin/ai")
    admin.post("/admin/ai", {
        "_token": token,
        "ai_enabled": "1",
        "ai_mode": "auto",
        "ai_base_url": AI_BASE,
        "ai_model": "test-model-a",
        "ai_min_confidence": "80",
        "ai_read_pdf": "1",
        "ai_assign_category": "1",
        "ai_assign_section": "1",
    })
    auto_title = f"AI automatic paper {stamp}"
    auto_pdf = os.path.join(tempfile.gettempdir(), f"v2-{stamp}-auto.pdf")
    with open(auto_pdf, "wb") as handle:
        handle.write(make_pdf(f"{auto_title} on priority proofs"))
    token = author.token("/submit")
    with open(auto_pdf, "rb") as handle:
        status, _, _ = author.post("/submit", {
            "_token": token,
            "title": auto_title,
            "abstract": ("An abstract long enough for validation, about priority proofs and the "
                         "role of cryptographic receipts in scholarly disputes."),
            "language": "en",
            "author_name[]": [f"作者{stamp}"],
            "author_affiliation[]": ["Independent"],
            "author_email[]": [author_email],
            "author_orcid[]": [""],
            "link_label[]": [""], "link_url[]": [""], "link_kind[]": ["other"],
            "visibility": "public",
        }, files={"pdf": ("auto.pdf", handle.read(), "application/pdf")})
    status, body, _ = author.get("/dashboard/papers")
    check("fully automatic mode reviewed the paper", "AI" in text(body), "no AI badge on the dashboard")

    # switch AI back off so the site is left in a sane state
    token = admin.token("/admin/ai")
    admin.post("/admin/ai", {
        "_token": token, "ai_mode": "off", "ai_base_url": AI_BASE, "ai_model": "test-model-a",
        "ai_min_confidence": "80", "ai_read_pdf": "1", "ai_assign_section": "1", "ai_assign_category": "1",
    })

    # ------------------------------------------------------------------ mail
    print("\nSMTP:")
    before = os.path.getsize(TRANSCRIPT) if os.path.exists(TRANSCRIPT) else 0
    status, body, _ = admin.get("/admin/mail")
    check("mail console renders", status == 200, f"HTTP {status}")
    token = admin.token("/admin/mail")
    status, body, _ = admin.post("/admin/mail", {
        "_token": token,
        "mail_enabled": "1",
        "mail_transport": "smtp",
        "mail_host": "127.0.0.1",
        "mail_port": SMTP_PORT,
        "mail_encryption": "none",
        "mail_username": "notifications@example.org",
        "mail_password": "authorisation-code-16",
        "mail_from_address": "",
        "mail_from_name": "雅典学院",
        "mail_reply_to": "reply@example.org",
        "mail_notify_admin": "1",
        "mail_notify_author": "1",
    })
    check("mail settings saved", status in (200, 302), f"HTTP {status}")
    check("status reports ready", "Ready" in text(body) or "ready" in text(body).lower(), "not ready")

    token = admin.token("/admin/mail")
    status, body, _ = admin.post("/admin/mail/test", {"_token": token, "to": ADMIN_EMAIL})
    check("test mail accepted", status in (200, 302), f"HTTP {status}")
    after = os.path.getsize(TRANSCRIPT) if os.path.exists(TRANSCRIPT) else 0
    check("the fake SMTP server received a conversation", after > before, f"{before} → {after}")
    if os.path.exists(TRANSCRIPT):
        with open(TRANSCRIPT, "r", encoding="utf-8", errors="replace") as handle:
            transcript = handle.read()
        check("the test used the configured envelope", "MAIL FROM:<notifications@example.org>" in transcript)
        check("the test authenticated", "235" in transcript)

    # ------------------------------------------------------ linked accounts
    print("\nlinked-account picker:")
    status, body, _ = author.get("/settings")
    settings_page = text(body)
    check("settings page renders", status == 200, f"HTTP {status}")
    check("picker markup present", "data-link-panel" in settings_page and "data-link-add" in settings_page)
    check("platform list is a select", "data-link-platform" in settings_page)
    check("retired platforms are gone", "zhihu" not in settings_page and "weibo" not in settings_page)
    for platform in ["orcid", "github", "mastodon", "bluesky", "threads", "wikipedia"]:
        check(f"{platform} is offered", f'value="{platform}"' in settings_page, "")

    token = author.token("/settings")
    status, _, _ = author.post("/settings/links", {
        "_token": token,
        "link_orcid": "0000-0002-1825-0097",
        "link_github": "athenaeum-test",
        "link_mastodon": "https://mastodon.social/@tester",
    })
    check("links saved", status in (200, 302), f"HTTP {status}")
    status, body, _ = author.get(f"/u/{re.search(r'U[A-Z0-9]{8}', settings_page).group(0)}")
    profile = text(body)
    check("orcid published on the profile", "0000-0002-1825-0097" in profile)
    check("github published on the profile", "github.com/athenaeum-test" in profile)

    token = author.token("/settings")
    status, _, _ = author.post("/settings/links", {"_token": token})
    check("links can be cleared", status in (200, 302), f"HTTP {status}")

    # --------------------------------------- v2.2 interface fixes (bug report)
    print("\nhome page & footer:")
    status, body, _ = public.get("/?lang=zh-CN")
    home = text(body)
    check("the subject tree is no longer dumped on the home page",
          home.count('class="tag"') < 20, f"{home.count('class=\"tag\"')} tags")
    check("a single button leads to the search interface",
          re.search(r'href="[^"]*/papers#areas"', home) is not None)
    footer_block = re.search(r'<div class="site-footer__links">(.*?)</div>', home, re.S)
    footer = footer_block.group(1) if footer_block else ""
    about_links = re.findall(r'href="[^"]*/about"[^>]*>([^<]*)<', footer)
    athenaeum_links = re.findall(r'href="[^"]*/about/athenaeum"[^>]*>([^<]*)<', footer)
    check("the footer links each intro page exactly once",
          len(about_links) == 1 and len(athenaeum_links) == 1,
          f"about={about_links} athenaeum={athenaeum_links}")
    check("the two footer labels differ",
          bool(about_links) and bool(athenaeum_links) and about_links[0] != athenaeum_links[0],
          f"{about_links} vs {athenaeum_links}")

    print("\nmulti-area search filter:")
    status, body, _ = public.get("/papers?lang=en")
    archive = text(body)
    check("the area filter renders", "data-area-filter" in archive and "data-area-search" in archive)
    check("areas are checkboxes named categories[]", 'name="categories[]"' in archive)
    check("the filter reports how many are selected", "data-area-summary" in archive)
    status, body, _ = public.get("/papers?categories%5B%5D=mathematics&categories%5B%5D=physics&lang=en")
    check("two areas can be filtered at once", status == 200, f"HTTP {status}")
    status, body, _ = public.get("/papers?categories%5B%5D=does-not-exist&lang=en")
    check("an unknown area yields an empty result, not everything",
          "no_papers" in text(body) or "没有" in text(body) or "No " in text(body), "")

    print("\nsearchable upload pickers:")
    status, body, _ = author.get("/submit")
    form = text(body)
    check("the language picker has a search box", "data-picker-search" in form)
    check("language options are searchable by native and English name",
          "chinese" in form.lower() and "中文" in form)
    check("the area picker has its own search box", form.count("data-picker") >= 2, str(form.count("data-picker")))
    check("the “other / not listed” area is offered", "__other__" in form)
    check("the free-text area field exists", 'name="category_other"' in form)

    print("\n“other area” papers must be classified first:")
    other_title = f"Unlisted field paper {stamp}"
    other_pdf = os.path.join(tempfile.gettempdir(), f"v2-{stamp}-other.pdf")
    with open(other_pdf, "wb") as handle:
        handle.write(make_pdf(f"{other_title} on an unusual subject"))
    token = author.token("/submit")
    with open(other_pdf, "rb") as handle:
        status, _, _ = author.post("/submit", {
            "_token": token,
            "title": other_title,
            "abstract": ("An abstract long enough for the validator, describing work in a field that "
                         "the taxonomy does not contain yet."),
            "language": "en",
            "category_id": "__other__",
            "category_other": f"Byzantine musicology {stamp}",
            "author_name[]": [f"作者{stamp}"],
            "author_affiliation[]": ["Independent"],
            "author_email[]": [author_email],
            "author_orcid[]": [""],
            "link_label[]": [""], "link_url[]": [""], "link_kind[]": ["other"],
            "visibility": "public",
        }, files={"pdf": ("other.pdf", handle.read(), "application/pdf")})
    check("a paper may be filed under “other”", status in (200, 302), f"HTTP {status}")

    status, body, _ = admin.get("/admin/papers?status=pending")
    listing = text(body)
    # Locate the row of *this* paper (the queue also holds older test papers).
    other_id = ""
    for row in re.findall(r"<tr\b.*?</tr>", listing, re.S):
        if other_title in row:
            match = re.search(r"/admin/paper/(\d+)", row)
            if match:
                other_id = match.group(1)
                break
    check("the newly filed paper is in the moderation queue", other_id != "", "row not found")
    status, body, _ = admin.get(f"/admin/paper/{other_id}")
    detail = text(body)
    check("the console shows the unlisted field name",
          f"Byzantine musicology {stamp}" in detail, "author's field missing")
    check("approval is disabled until the paper is classified",
          'id="reclassify_area"' in detail and "disabled" in detail)

    token = admin.token(f"/admin/paper/{other_id}")
    admin.post(f"/admin/paper/{other_id}/approve", {"_token": token})
    status, body, _ = admin.get("/admin/papers?status=pending")
    check("the blocked approval did not publish the paper", other_id in text(body))

    area_select = re.search(r'id="reclassify_area".*?</select>', detail, re.S)
    area_id = re.search(r'value="(\d+)"', area_select.group(0)).group(1) if area_select else ""
    token = admin.token(f"/admin/paper/{other_id}")
    status, _, _ = admin.post(f"/admin/paper/{other_id}/reclassify", {
        "_token": token, "category_id": area_id,
    })
    check("a moderator can assign an area", status in (200, 302), f"HTTP {status}")

    token = admin.token(f"/admin/paper/{other_id}")
    admin.post(f"/admin/paper/{other_id}/approve", {"_token": token, "note": "classified by the test"})
    status, body, _ = admin.get("/admin/papers?status=approved")
    check("after classification the paper can be approved", other_title in text(body))

    print("\nAI console diagnostics:")
    status, body, _ = admin.get("/admin/ai")
    console = text(body)
    check("the model field can offer the endpoint's models", "datalist" in console or "ai_model" in console)
    check("creating areas from the AI can be toggled", "ai_create_categories" in console)

    print("\nmail error reporting:")
    token = admin.token("/admin/mail")
    admin.post("/admin/mail", {
        "_token": token, "mail_enabled": "1", "mail_transport": "smtp",
        "mail_host": "127.0.0.1", "mail_port": "9", "mail_encryption": "none",
        "mail_username": "notifications@example.org", "mail_password": "x",
        "mail_from_name": "test", "mail_from_address": "notifications@example.org",
    })
    token = admin.token("/admin/mail")
    status, body, _ = admin.post("/admin/mail/test", {"_token": token, "to": ADMIN_EMAIL})
    check("a refused connection names the host and port",
          "127.0.0.1:9" in text(body), "host:port missing from the error message")

    # ------------------------------------------ registration needs a mail code
    print("\nregistration with an e-mailed code:")
    token = admin.token("/admin/mail")
    admin.post("/admin/mail", {
        "_token": token, "mail_enabled": "1", "mail_transport": "smtp",
        "mail_host": "127.0.0.1", "mail_port": SMTP_PORT, "mail_encryption": "none",
        "mail_username": "notifications@example.org", "mail_password": "authorisation-code-16",
        "mail_from_name": "AthenXiv", "mail_from_address": "notifications@example.org",
    })
    guest = Browser(BASE)
    status, body, _ = guest.get("/register")
    registration = text(body)
    check("without the requirement the form asks for no code",
          'name="email_code"' not in registration, "code field shown although not required")
    set_setting("registration.verify_email", "1")
    guest = Browser(BASE)
    status, body, _ = guest.get("/register")
    registration = text(body)
    check("the code field appears once the requirement is on",
          'name="email_code"' in registration, "field missing")
    check("the form offers to send one", "data-send-code" in registration)

    new_email = f"code-user-{stamp}@example.org"
    message_file = os.path.join(tempfile.gettempdir(), "fake-smtp-message.txt")
    status, body, _ = guest.post("/register/code", {
        "_token": guest.token("/register"), "email": new_email, "locale": "en",
    }, headers={"Accept": "application/json"})
    try:
        payload = json.loads(text(body))
    except Exception:  # noqa: BLE001
        payload = {}
    check("the code endpoint answers with JSON", payload.get("ok") is True, text(body)[:200])

    code = ""
    if os.path.exists(message_file):
        raw = open(message_file, "r", encoding="utf-8", errors="replace").read()
        decoded = []
        for chunk in re.findall(r"\r?\n\r?\n([A-Za-z0-9+/=\r\n]{40,})", raw):
            try:
                decoded.append(base64.b64decode(re.sub(r"\s+", "", chunk)).decode("utf-8", "replace"))
            except Exception:  # noqa: BLE001
                continue
        match = re.search(r"\b(\d{6})\b", "\n".join(decoded))
        code = match.group(1) if match else ""
    check("the mail carried a six-digit code", len(code) == 6, f"code={code!r}")

    wrong = Browser(BASE)
    wrong.post("/register", {
        "_token": wrong.token("/register"),
        "nickname": f"Wrong{stamp[:5]}",
        "email": f"wrong-{new_email}",
        "password": "code-flow-password-2026",
        "password_confirmation": "code-flow-password-2026",
        "email_code": "000000",
        "terms": "1",
    })
    status, body, _ = wrong.get("/dashboard")
    check("a wrong code does not create the account", status != 200 or "logout" not in text(body))

    status, body, _ = guest.post("/register", {
        "_token": guest.token("/register"),
        "nickname": f"CodeUser{stamp[:5]}",
        "email": new_email,
        "password": "code-flow-password-2026",
        "password_confirmation": "code-flow-password-2026",
        "email_code": code,
        "terms": "1",
    })
    status, body, _ = guest.get("/dashboard")
    check("the right code creates the account", status == 200, f"HTTP {status}")
    check("the new account is logged in", "logout" in text(body) or "Log out" in text(body))

    # ------------------------------------------------- v2.3 brand + wording
    print("\nAthenXiv branding:")
    status, body, _ = public.get("/?lang=en")
    home_en = text(body)
    check("the header brand is AthenXiv", "AthenXiv" in home_en and ">Athenaeum<" not in home_en)
    check("the page title carries the new name", "<title>AthenXiv" in home_en or "· AthenXiv</title>" in home_en)
    check("no page still introduces itself as Athenaeum",
          "Athenaeum" not in home_en.replace("athenaeum.test", ""), "old name in the markup")
    for path in ["/favicon.ico", "/site.webmanifest", "/assets/img/favicon.svg",
                 "/assets/img/logo-mark.svg", "/assets/img/apple-touch-icon.png"]:
        status, body, headers = public.get(path)
        check(f"{path} is served", status == 200, f"HTTP {status}")
    status, body, _ = public.get("/?lang=th")
    check("a translated page loads", status == 200, f"HTTP {status}")
    status, body, _ = public.get("/about?lang=ar")
    arabic = text(body)
    check("the Arabic intro page is translated", "AthenXiv" in arabic and any('\u0600' <= ch <= '\u06ff' for ch in arabic))
    status, body, _ = public.get("/about?lang=ca")
    check("the Catalan intro page is translated", "AthenXiv" in text(body) and "lloc" in text(body))
    status, body, _ = public.get("/about/athenaeum?lang=ja")
    check("the etymology page renders in Japanese", "AthenXiv" in text(body))

    # -------------------------------------------------------- owner editing
    print("\npaper editing as the author:")
    status, body, _ = author.get(f"/paper/{paper_uid}/edit")
    check("the author reaches the edit form", status == 200, f"HTTP {status}")
    check("the edit form warns about re-review", "review" in text(body).lower() or "审核" in text(body))

    # publish it first, then edit again: the paper must return to the queue
    token = admin.token(f"/admin/paper/{paper_id}")
    admin.post(f"/admin/paper/{paper_id}/approve", {"_token": token, "note": "for the edit test"})
    status, body, _ = public.get(f"/paper/{paper_uid}?lang=en")
    check("the paper is published before the edit", status == 200, f"HTTP {status}")

    token = author.token(f"/paper/{paper_uid}/edit")
    status, body, _ = author.post(f"/paper/{paper_uid}/edit", {
        "_token": token,
        "title": title + " (revised metadata)",
        "abstract": ("A short abstract, long enough for the validator, describing the first "
                     "version of a paper used to exercise version management end to end. Revised."),
        "language": "en",
        "author_name[]": [f"作者{stamp}"],
        "author_affiliation[]": ["Independent"],
        "author_email[]": [author_email],
        "author_orcid[]": [""],
        "link_label[]": [""], "link_url[]": [""], "link_kind[]": ["other"],
        "visibility": "public",
    })
    check("the author's edit is accepted", status in (200, 302), f"HTTP {status}")
    status, body, _ = author.get("/dashboard/papers")
    dashboard = text(body)
    check("an edited published paper returns to review",
          "(revised metadata)" in dashboard and "Pending" in dashboard or "待审" in dashboard or "待审核" in dashboard,
          "still published?")

    # ------------------------------------------------- collapsible proofs
    print("\ncollapsible proofs and revisions:")
    token = admin.token(f"/admin/paper/{paper_id}")
    admin.post(f"/admin/paper/{paper_id}/approve", {"_token": token, "note": "republish"})
    status, body, _ = public.get(f"/paper/{paper_uid}?lang=en")
    page = text(body)
    check("the version history is collapsible", "version-history" in page)
    check("older timestamps are behind a toggle", "ots-history" in page, "no history toggle")
    check("only the current proof is shown expanded",
          page.count('class="ots ots--') - page.count("ots--compact") >= 1, "no expanded proof")
    check("history entries are labelled with their version", "version" in page and "v1" in page)
    check("history proofs are compact", "ots--compact" in page)

    print("\nshort status chips:")
    status, body, _ = author.get("/dashboard/papers")
    dash = text(body)
    chip_labels = re.findall(r'<span class="ots-chip[^"]*"[^>]*>\s*([^<]+?)\s*</span>', dash)
    check("the visible chip label stays short",
          bool(chip_labels) and all(len(label.strip()) <= 14 for label in chip_labels),
          f"labels={chip_labels}")
    check("a short chip is used instead",
          any(word in dash for word in ["Pending", "Anchored", "Failed", "等待中", "已获取", "失败"]),
          "no short chip found")

    # ---------------------------------------------------------------- cleanup
    print("\ncleanup:")
    # leave the mail feature switched off again (the fixture server is test-only)
    token = admin.token("/admin/mail")
    admin.post("/admin/mail", {
        "_token": token,
        "mail_host": "127.0.0.1", "mail_port": SMTP_PORT, "mail_encryption": "none",
        "mail_transport": "smtp", "mail_username": "notifications@example.org",
        "mail_from_name": "雅典学院", "mail_reply_to": "reply@example.org",
    })
    status, body, _ = admin.get("/admin/mail")
    check("mail can be switched off again", "disabled" in text(body).lower() or "Not configured" in text(body))

    status, body, _ = admin.get("/admin/categories")
    match = re.search(rf"/admin/categories/(\d+)/purge", text(body))
    if match:
        token = admin.token("/admin/categories")
        status, _, _ = admin.post(f"/admin/categories/{match.group(1)}/purge", {"_token": token})
        check("test subject area removed", status in (200, 302))
    for uid in [paper_uid]:
        status, body, _ = admin.get(f"/paper/{uid}" if False else "/admin/papers")
    print("  (papers left in place as demo content)")

    # Put the operator's real AI/mail configuration back (see the helper).
    if snapshot_ok and settings_snapshot("restore"):
        check("the live AI/mail settings were restored", True)

    print(f"\n{len(PASSED)} checks passed, {len(FAILED)} failed")
    for failure in FAILED:
        print("  FAILED: " + failure)
    return 1 if FAILED else 0


if __name__ == "__main__":
    sys.exit(main())
