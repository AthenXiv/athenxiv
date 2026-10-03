# Parsedown 1.7.4 (vendored)

| | |
|---|---|
| Upstream | [erusev/parsedown](https://github.com/erusev/parsedown) |
| Version | **1.7.4** (`codeload.github.com/erusev/parsedown/tar.gz/refs/tags/1.7.4`) |
| Licence | MIT — see `Parsedown.LICENSE` |
| File | `Parsedown.php` (42 KB, single class, no dependencies) |
| Vendored on | 2026-10-02 |

## Why a single file and not Composer

AthenXiv has **no Composer runtime dependencies** on purpose: it must run on an
ordinary shared host where only `public/` is uploaded and `composer install`
is not available. `app/Core/Markdown.php` loads this file if it exists and falls
back to a small built-in renderer otherwise, so the application still works if
the file is deleted.

Usage:

```php
$parser = new Parsedown();
$parser->setSafeMode(true);      // escapes raw HTML, blocks javascript: URLs
$parser->setBreaksEnabled(true); // single newlines become <br>
$html = $parser->text($markdown);
```

`Markdown::render()` additionally hardens the output: `<script>`, `<iframe>`,
`<object>`, `<embed>`, `<form>` and `on*=` handlers are stripped, and external
links get `rel="nofollow ugc noopener noreferrer" target="_blank"`.

## Local modification

Two method signatures were adjusted for PHP 8.4, which deprecates implicitly
nullable parameters. This is the only change to the upstream file:

```diff
-    protected function blockSetextHeader($Line, array $Block = null)
+    protected function blockSetextHeader($Line, ?array $Block = null)
-    protected function blockTable($Line, array $Block = null)
+    protected function blockTable($Line, ?array $Block = null)
```

Upstream fixed this in the 1.8.0 line; 1.7.4 is kept because it is the stable
tag. Re-apply the two-line patch after any upgrade, then run:

```
php -d error_reporting=E_ALL tests/lang_audit.php
```

(there is no dedicated Parsedown test; any page with a Markdown profile page
exercises it, and `php -l app/Support/Parsedown.php` must pass).
