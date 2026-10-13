# Changelog

All notable changes to `dskripchenko/laravel-php-pdf` are documented in
this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] — 2026-10-13

### Added
- **Digital signatures** — a `signing` config block (PEM certificate and key
  or PKCS#12, intermediate chain, PAdES or PKCS#7 format, default reason /
  location / contact, RFC 3161 timestamp authority) and:
  - `Pdf::fromHtml(...)->signed($options)` / `Pdf::render(...)->signed()` —
    sign a generated document; its SignatureField is signed with the stamp
    filled from the certificate, otherwise an invisible signature is added;
  - `Pdf::sign($pathOrBytes, $options)` — sign an existing or already signed
    PDF (incremental: earlier signatures stay valid); options for the field,
    certification (DocMDP), field locks, an external `Signer`, the stamp;
  - `Pdf::signer($pdf)` — php-pdf's `PdfSigner` for visible fields, fields
    for later signers, signing outside the server;
  - `Pdf::signatureConfig($options)`, `Pdf::timestampAuthority()`;
  - `Pdf::ltv($pdf)` — long-term validation (DSS, then a document
    timestamp when a TSA is configured);
  - `Pdf::validate($pdf)` with a `validation` config block (trust anchors,
    system roots, revocation fetching).
- `Signing\LaravelHttpTransport` — timestamp authorities, OCSP responders
  and CRLs go through Laravel's HTTP client (proxies, `Http::fake()`).

### Changed
- Requires `dskripchenko/php-pdf` ^1.15. Besides the signing features this
  brings fixes that matter in long-running workers (Octane): a `PdfFont`
  kept by the factory no longer holds every finished document in memory,
  and a cached `PdfImage` no longer points at the wrong object in the next
  document; encrypted documents with embedded fonts are no longer corrupt.

## [1.2.0] — 2026-10-01

### Added
- `Pdf::fillForm($pathOrBytes, $password = '')` — opens an existing PDF form
  as php-pdf's `ExistingFormFiller` (fill, flatten, stamp), with the
  configured `fonts.default` used for values the form's own fonts cannot
  show.
- `response()->pdf()` accepts a filled form.

### Changed
- Requires `dskripchenko/php-pdf` ^1.10.

## [1.1.1] — 2026-07-28

### Changed
- CI: PHP **8.5** added to the test matrix — the bridge is verified
  across PHP 8.2–8.5 × Laravel 11/12/13.

## [1.1.0] — 2026-07-18

### Added
- Streamed HTTP responses: `PendingPdf::stream()` and
  `PendingPdf::streamDownload()` render the document straight into the
  output buffer via php-pdf's streaming emitter — the PDF is never held
  in memory as one string. Streamed output is byte-identical to the
  buffered path (covered by a parity test).

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
