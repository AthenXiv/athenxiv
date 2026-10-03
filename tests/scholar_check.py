#!/usr/bin/env python3
"""Google Scholar compliance check for AthenXiv.

Checks what Scholar's inclusion guidelines actually ask for, on a live site:

  1. a paper landing page is crawlable (200, no noindex, not blocked by robots.txt)
  2. it carries the Highwire/`citation_*` metadata Scholar parses
     (title, at least one author, a real publication date, an absolute PDF URL)
  3. that PDF URL ends in `.pdf` and really answers with Content-Type:
     application/pdf (an HTML page behind a .pdf URL is a classic silent failure)
  4. the PDF is fetchable like a crawler would: no cookies, no referer
  5. the sitemap lists paper URLs, so they can be discovered at all

Usage:  python tests/scholar_check.py [base-url] [--papers N]
"""

from __future__ import annotations

import json
import re
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request

BASE = sys.argv[1] if len(sys.argv) > 1 else "https://athenxiv.com"
PAPERS = 5
if "--papers" in sys.argv:
    PAPERS = int(sys.argv[sys.argv.index("--papers") + 1])

CTX = ssl.create_default_context()
CRAWLER_UA = "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)"

checks = 0
failures: list[str] = []


def check(label: str, ok: bool, detail: str = "") -> None:
    global checks
    checks += 1
    mark = "ok  " if ok else "FAIL"
    line = f"  [{mark}] {label}"
    if detail and not ok:
        line += f" — {detail}"
    print(line)
    if not ok:
        failures.append(label)


def fetch(url: str, headers: dict[str, str] | None = None) -> tuple[int, bytes, dict[str, str]]:
    request = urllib.request.Request(url, headers=headers or {"User-Agent": CRAWLER_UA})
    try:
        with urllib.request.urlopen(request, timeout=90, context=CTX) as response:
            return response.status, response.read(), dict(response.headers)
    except urllib.error.HTTPError as error:
        return error.code, error.read(), dict(error.headers)
    except Exception as error:  # noqa: BLE001
        return 0, str(error).encode(), {}


def meta(html: str, name: str) -> list[str]:
    """Read a citation meta tag regardless of the attribute order."""
    values = []
    for tag in re.findall(r"<meta\b[^>]*>", html, re.I):
        if re.search(rf'name=["\']{re.escape(name)}["\']', tag, re.I):
            match = re.search(r'content=["\']([^"\']*)["\']', tag, re.I)
            values.append(match.group(1) if match else "")
    return values


print(f"Google Scholar compliance — {BASE}\n")

# --- robots.txt first: if it blocks the papers, nothing else matters ---------
status, body, _ = fetch(BASE + "/robots.txt")
robots = body.decode("utf-8", "replace")
print("robots.txt")
check("robots.txt is served", status == 200, f"HTTP {status}")
disallow = [line.split(":", 1)[1].strip() for line in robots.splitlines()
            if line.lower().startswith("disallow:")]
blocking = [rule for rule in disallow if rule in ("/", "/paper", "/paper/") or rule.startswith("/paper")]
check("papers are not disallowed", not blocking, ", ".join(blocking))
sitemap = re.search(r"(?im)^sitemap:\s*(\S+)", robots)
check("robots.txt points at a sitemap", sitemap is not None, robots.strip()[:80])

# --- sitemap -----------------------------------------------------------------
if sitemap:
    status, body, _ = fetch(sitemap.group(1))
    text = body.decode("utf-8", "replace")
    check("sitemap is served", status == 200, f"HTTP {status}")
    check("sitemap lists paper URLs", "/paper/" in text, f"{len(text)} bytes")

# --- landing pages and their PDF links ---------------------------------------
status, home_body, _ = fetch(BASE + "/")
home = home_body.decode("utf-8", "replace")
# Discover the browse URL from the home page: on a host without URL rewriting
# it is /index.php/papers, locally it is /papers.
found = re.search(r'href="([^"]*/papers)"', home)
listing_url = urllib.parse.urljoin(BASE + "/", found.group(1)) if found else BASE + "/papers"
check("the home page links to the paper list", found is not None, listing_url)

status, body, _ = fetch(listing_url)
listing = body.decode("utf-8", "replace")
# Use the links the site itself publishes: on a host without URL rewriting they
# carry the front controller (/index.php/paper/…), locally they do not.
landing_urls = list(dict.fromkeys(
    urllib.parse.urljoin(BASE + "/", href)
    for href in re.findall(r'href="([^"]*/paper/ATH-[A-Za-z0-9]+)"', listing)
))[:PAPERS]
check("the paper list is crawlable", status == 200 and bool(landing_urls),
      f"HTTP {status}, {len(landing_urls)} papers")

print("\nlanding pages")
for landing in landing_urls:
    uid = re.search(r"(ATH-[A-Za-z0-9]+)", landing).group(1)
    status, body, headers = fetch(landing)
    html = body.decode("utf-8", "replace")
    check(f"{uid}: landing page is 200", status == 200, f"HTTP {status}")
    check(f"{uid}: not noindex", "noindex" not in headers.get("X-Robots-Tag", "").lower()
          and not re.search(r'name=["\']robots["\'][^>]*noindex', html, re.I))
    title = meta(html, "citation_title")
    authors = meta(html, "citation_author")
    date = meta(html, "citation_publication_date")
    pdf = meta(html, "citation_pdf_url")
    check(f"{uid}: citation_title", bool(title and title[0].strip()), str(title))
    check(f"{uid}: citation_author", bool(authors), f"{len(authors)} author tag(s)")
    check(f"{uid}: citation_publication_date YYYY/MM/DD",
          bool(date and re.match(r"^\d{4}/\d{1,2}/\d{1,2}$", date[0])), str(date))
    check(f"{uid}: citation_pdf_url is absolute", bool(pdf and pdf[0].startswith("http")), str(pdf))
    if pdf:
        target = pdf[0]
        check(f"{uid}: the PDF URL ends in .pdf", target.lower().endswith(".pdf"), target)
        pdf_status, pdf_body, pdf_headers = fetch(target)
        ctype = pdf_headers.get("Content-Type", "")
        check(f"{uid}: PDF responds 200 without cookies", pdf_status == 200, f"HTTP {pdf_status}")
        check(f"{uid}: Content-Type is application/pdf", ctype.startswith("application/pdf"), ctype)
        check(f"{uid}: body is really a PDF", pdf_body[:5] == b"%PDF-", repr(pdf_body[:12]))

        # The citation export (BibTeX/RIS/JSON) is what other tools read; it has
        # to advertise the same URL, not an older file route.
        export_status, export_body, _ = fetch(landing + "/cite?format=json")
        try:
            exported = json.loads(export_body.decode("utf-8", "replace"))
        except Exception:  # noqa: BLE001
            exported = {}
        check(f"{uid}: the citation export names the same PDF",
              export_status == 200 and exported.get("pdf") == target,
              f"HTTP {export_status}: {exported.get('pdf')!r}")

        # The reader page is a tool, not a landing page: it must not be indexed
        # as a second copy of the paper.
        viewer_status, viewer_body, _ = fetch(landing + "/preview")
        viewer = viewer_body.decode("utf-8", "replace")
        check(f"{uid}: the viewer page is noindex",
              viewer_status == 200 and re.search(r'name=["\']robots["\'][^>]*noindex', viewer, re.I) is not None,
              f"HTTP {viewer_status}")

print()
print(f"{checks} checks, {len(failures)} failed")
if failures:
    for item in failures:
        print("  - " + item)
    sys.exit(1)
print("SCHOLAR CHECKS OK")
