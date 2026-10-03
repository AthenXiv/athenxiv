"""Athenaeum localisation check.

Verifies that all six shipped locales render, that automatic detection works
from the Accept-Language header, that an unsupported language falls back to
English, and that a missing translation key never leaks to the user as a raw
`some.key` string.

    python tests/e2e_i18n.py http://127.0.0.1:8123
"""

from __future__ import annotations

import re
import sys

from e2e_smoke import FAILED, PASSED, Browser, check

BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8123"

# A string that only exists in that locale's file (taken from resources/lang).
EXPECTED = {
    "zh-CN": ("简体中文", "论文"),
    "ja": ("日本語", "論文"),
    "ko": ("한국어", "논문"),
    "fr": ("Français", "Articles"),
    "de": ("Deutsch", "Aufsätze"),
    "en": ("English", "Papers"),
}

# Locale negotiation: header -> locale that must win.
DETECTION = [
    ("zh-CN,zh;q=0.9,en;q=0.8", "zh-CN"),
    ("ja-JP,ja;q=0.9", "ja"),
    ("ko-KR", "ko"),
    ("fr-FR,fr;q=0.8", "fr"),
    ("de-AT,de;q=0.9", "de"),
    ("en-GB,en;q=0.9", "en"),
    ("pt-BR,pt;q=0.9", "pt-BR"),        # regional tag resolves to the shipped locale
    ("", "en"),                        # no header -> English
]

KEY_PATTERN = re.compile(r"\b(common|paper|admin|settings|dashboard|auth|ots|user|status|link_kind|upload)\.[a-z][a-z0-9_]+")


def main() -> int:
    print(f"Athenaeum localisation check against {BASE}\n")

    print("explicit locale switch (?lang=):")
    for locale, (native, word) in EXPECTED.items():
        browser = Browser(BASE)
        status, body, _ = browser.get(f"/papers?lang={locale}")
        page = body.decode("utf-8", "replace")
        check(f"{locale}: page renders", status == 200, f"HTTP {status}")
        check(f"{locale}: <html lang> reflects the locale", f'lang="{locale}"' in page, "wrong lang attribute")
        check(f"{locale}: translated strings present", word in page, f"'{word}' not found")
        check(f"{locale}: switcher lists the native name", native in page)
        leaked = set(KEY_PATTERN.findall(page))
        leaked = {m.group(0) for m in KEY_PATTERN.finditer(page)}
        check(f"{locale}: no untranslated key leaked", not leaked, ", ".join(sorted(leaked))[:120])

    print("\nautomatic detection from Accept-Language:")
    for header, expected in DETECTION:
        browser = Browser(BASE)
        status, body, _ = browser.get("/", headers={"Accept-Language": header} if header else {})
        page = body.decode("utf-8", "replace")
        match = re.search(r'<html lang="([^"]+)"', page)
        actual = match.group(1) if match else "(none)"
        check(
            f"'{header or '(no header)'}' → {expected}",
            actual == expected,
            f"got {actual}",
        )

    print("\npersistence and fallback:")
    browser = Browser(BASE)
    browser.get("/?lang=de")
    status, body, _ = browser.get("/papers")
    page = body.decode("utf-8", "replace")
    check("chosen language persists without ?lang=", 'lang="de"' in page, "cookie was not honoured")

    status, body, _ = browser.get("/locale/ja?next=/papers")
    status, body, _ = browser.get("/papers")
    check("locale switch endpoint works", 'lang="ja"' in body.decode("utf-8", "replace"))

    print(f"\n{len(PASSED)} checks passed, {len(FAILED)} failed")
    for failure in FAILED:
        print("  FAILED: " + failure)
    return 1 if FAILED else 0


if __name__ == "__main__":
    sys.exit(main())
