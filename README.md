# AthenXiv

An open, multidisciplinary archive for research papers, with **OpenTimestamps**
proofs, in-browser PDF reading, and a thirty-language interface.

Live site: <https://athenxiv.com/>

AthenXiv accepts a paper from anyone — no degree, position, affiliation or
sponsor is required. Every upload is hashed and stamped with OpenTimestamps, so
readers can verify that a file existed at a given moment. The archive also
preserves open-access works published elsewhere, marked as such and shown with
their original publication date and licence.

## What is in the box

* **Zero dependencies.** No Composer, no npm build step: plain PHP 8.1+ with a
  small MVC core, PDO for storage, and vanilla CSS/JS served straight from disk.
* **Dual database.** MySQL (production) and SQLite (development and tests) share
  one schema layer; the SQL is written once and rewritten per driver.
* **OpenTimestamps.** Uploads get a `.ots` proof, calendars are aggregated, and
  proofs upgrade from pending to confirmed on their own.
* **PDF.js viewer** served through the application, so hosts that do not know
  `.mjs` still render correctly.
* **Thirty languages** with `Accept-Language` negotiation and per-page hreflang,
  plus administrator-editable content pages.
* **Google Scholar ready**: Highwire `citation_*` metadata, a `/paper/{uid}.pdf`
  URL that answers `Content-Type: application/pdf`, and a sitemap generated from
  the database on every request.
* **Moderation workflow** with an optional AI pre-review, version history that
  keeps unreviewed revisions away from readers, and a crawler-visit journal for
  hosts whose access log is out of reach.

## Requirements

* PHP 8.1 or newer with `pdo`, `pdo_mysql` or `pdo_sqlite`, `mbstring`, `json`,
  `curl` and `openssl`
* MySQL 5.7+ or SQLite 3
* A web server; the application supports two layouts — document root pointing at
  `public/`, or the whole project in the web root with a front controller
  (`/index.php/...`) for hosts without URL rewriting

## Install

```bash
git clone https://github.com/AthenXiv/athenxiv.git
cd athenxiv

# 1. configuration: copy the example and edit it
cp config/config.example.php config/config.local.php

# 2. database (SQLite is enough to look around)
php bin/install.php --driver=sqlite \
    --email=you@example.com --password='change-me-please' --nickname=Keeper
php bin/migrate.php

# 3. run it
php -S 127.0.0.1:8000 -t public public/router.php
```

Then open <http://127.0.0.1:8000/>. The administrator account is the one you
created in step 2.

For a production host, point the document root at `public/`. If the host cannot
do that (shared hosting usually cannot), copy the contents of `public/` into the
web root and set `app.front_controller` to `index.php` in your configuration, so
every URL is prefixed with `/index.php`.

## Tests

```bash
php tests/lang_audit.php                        # every key used in code exists in en.php
php tests/v2_test.php                           # services, models, mail, versions, OTS codec
php tests/e2e_smoke.py   http://127.0.0.1:8000  # public pages and flows
php tests/e2e_admin.py   http://127.0.0.1:8000  # administrator console
php tests/e2e_i18n.py    http://127.0.0.1:8000  # thirty languages
php tests/scholar_check.py https://athenxiv.com # Google Scholar compliance, live
```

The Python suites drive a running instance; the PHP ones run on their own,
against a throwaway SQLite database. Some suites need the fake external services
in `tests/fixtures/` (AI endpoint, calendar, SMTP). The credentials those suites
use (`*-password-2026`, the demo administrator) belong to databases the tests
create themselves — they are fixtures, not configuration.

## Layout

```
app/          Core (router, request, response, i18n, settings), Models, Services, Controllers
bin/          CLI: install, migrate, imports, translation appliers, OTS upgrade
config/       configuration template and your local override (never committed)
database/     schemas, seed data, translation sources for content pages
public/       web root: front controller, assets, vendored PDF.js
resources/    views (plain PHP templates) and thirty language files
routes/       route table
tests/        unit-ish and end-to-end suites, plus fixtures
```

## Contributing

Bug reports and pull requests are welcome. Please run the suites above before
opening a pull request, and keep the language files complete:
`tests/lang_audit.php` fails when a string used in a template is missing from
`resources/lang/en.php`.

## Licence

MIT — see [LICENSE](LICENSE). The papers and other content published on the live
site are **not** covered by this licence; each work keeps its own rights and
licence, which the site displays on the paper page.
