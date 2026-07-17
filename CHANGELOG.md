# Changelog

All notable changes to `dskripchenko/laravel-php-pdf` are documented in
this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] — 2026-07-17

### Added
- `PhpPdfServiceProvider` with package discovery (no manual registration)
  and publishable `config/php-pdf.php`.
- `Pdf` facade: `fromHtml()`, `builder()`, `render()`; `PendingPdf` with
  `bytes()`, `save()`, `inline()`, `download()`, `response()`.
- `response()->pdf($pdf, $filename, inline:)` macro accepting a
  `PendingPdf`, either php-pdf `Document` layer, or raw bytes.
- Config-driven page defaults (paper, orientation, margins), default
  `/Info` metadata, and embedded-TTF fonts including named families
  (`ConfigFontProvider`) for CSS `font-family`.
- CI matrix: PHP 8.2–8.4 × Laravel 11/12/13 (Laravel 13 on PHP 8.3+).
