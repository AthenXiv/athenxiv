# Vendored: PDF.js (prebuilt viewer)

| | |
|---|---|
| Upstream | [mozilla/pdf.js](https://github.com/mozilla/pdf.js) |
| Version | **4.10.38** (prebuilt distribution, `pdfjs-4.10.38-dist.zip` from the GitHub release) |
| Source | `https://github.com/mozilla/pdf.js/releases/download/v4.10.38/pdfjs-4.10.38-dist.zip` |
| Licence | Apache-2.0 — see `LICENSE` in this directory |
| Vendored on | 2026-10-02 |
| Total size | ≈ 7.2 MB, 369 files |

## Why the prebuilt zip and not the npm package

`pdfjs-dist` 4.x **no longer ships the viewer** in the npm tarball — `web/` only
contains the reusable components (`pdf_viewer.mjs`, `pdf_viewer.css`). The
standalone viewer (`viewer.html`, `viewer.mjs`, `viewer.css`, locales, cmaps) is
published as the release zip. Version 4.10.38 is the release that downloaded
successfully here; 4.8.69 and 4.10.38 are API-compatible for our usage.

## What was copied

```
public/vendor/pdfjs/
├── LICENSE
├── build/
│   ├── pdf.mjs            (658 KB)  — referenced by web/viewer.html
│   └── pdf.worker.mjs     (2.2 MB)  — default GlobalWorkerOptions.workerSrc
└── web/
    ├── viewer.html        (44 KB)
    ├── viewer.mjs         (478 KB)
    ├── viewer.css         (150 KB)
    ├── images/            (66 files, 70 KB)
    ├── locale/locale.json (2.5 KB — v4 uses a single Fluent bundle)
    ├── cmaps/             (169 files, 1.11 MB — Adobe-CNS1/GB1/Japan1/Korea1
    │                       included, so CJK PDFs without embedded fonts work)
    └── standard_fonts/    (16 files, 0.74 MB)
```

Deliberately **not** copied: `*.map` source maps (9 MB), `pdf.sandbox*.mjs`
(needs an iframe sandbox host we do not use), `web/debugger.*`, and the sample
`compressed.tracemonkey-pldi-09.pdf`.

## How the application embeds it

`PaperController::preview()` renders `resources/views/papers/preview.php`, which
points an iframe at the viewer and passes the PDF as a **same-origin path**:

```
/vendor/pdfjs/web/viewer.html?file=%2Fpaper%2FATH-XXXXXX%2Ffile
```

* `file=` must be URL-encoded (`/` → `%2F`); PDF.js parses it with
  `URLSearchParams` and then validates it against the viewer's own origin.
* Passing an absolute URL (`http://host/...`) also works, but a path is used so
  the viewer keeps working behind a reverse proxy that rewrites the host.
* The viewer's own `?v=` cache-buster from `asset()` is **not** appended, because
  it would collide with the `file=` parameter (two `?` in one query string).
* `resources/views/papers/show.php` embeds this preview route in an
  `<iframe class="pdf-frame">`; if `vendor/pdfjs/web/viewer.html` is missing the
  views fall back to `<object data="/paper/…/file" type="application/pdf">`, so
  the page degrades instead of breaking.

## Server configuration notes

* **MIME type**: Apache does not know `.mjs` and would serve the modules as
  `application/octet-stream`, breaking the viewer. `public/.htaccess` therefore
  contains `AddType text/javascript .mjs`. For nginx add:
  `types { text/javascript mjs; }`
* **Range requests**: PDF.js requests byte ranges. PDFs are streamed by
  `Response::file()` (PHP), which implements `206 Partial Content`,
  `Content-Range`, `ETag` and `304`, so no webserver support is required.
* The files are served as plain static assets; nothing routes them through PHP.

## Updating

1. Download a newer `pdfjs-<version>-dist.zip` from the pdf.js releases page.
2. Replace `build/`, `web/` and `LICENSE` with the same subset listed above.
3. Update the version table in this file.
4. Re-run `python tests/e2e_admin.py` and open any paper's *Read online* button:
   the toolbar, page navigation and CJK rendering must all still work.
