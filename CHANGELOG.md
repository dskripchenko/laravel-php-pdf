# Changelog

All notable changes to `dskripchenko/laravel-php-pdf` are documented in
this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `signing.signer` (`PHP_PDF_SIGNER`) — a container binding or class that
  resolves to a php-pdf `Signer` or `CmsSigner` (CryptoPro CSP, a PKCS#11
  token or HSM, a cloud KMS). It replaces the configured certificate and
  key for `Pdf::sign()`, `->signed()`, `Pdf::signatureConfig()` and
  `Pdf::signDetached()`, and is resolved once.
- `fonts.provider` — a php-pdf `FontProvider` (binding or class), asked for
  the families not listed in `fonts.families`; e.g. the Liberation fonts
  with their Arial / Times New Roman / Courier New aliases.

### Fixed
- The `bold`, `italic` and `bold_italic` faces of a family in
  `fonts.families` were never used: bold text in a configured family came
  out in the regular face. Each face now answers for itself; one that is
  not configured falls back to the regular face.
- `Pdf::signDetached()` with a `CmsSigner` failed with a TypeError; it now
  works with php-pdf 1.20+ and says so clearly on older versions.

### Changed
- Requires `dskripchenko/php-pdf` ^1.18 (was ^1.16) for `CmsSigner`.

## [1.4.0] — 2026-10-13

### Added
- **Network policy for signing** — `Signing\UrlPolicy` and the
  `signing.network` config block. Every request php-pdf makes through
  `LaravelHttpTransport` (TSA, OCSP, CRL, issuer certificates), and every
  redirect, passes it: only http/https, no credentials in URLs; with
  `block_private_networks`, every address the host resolves to must be
  public (loopback, RFC 1918, link-local, CGN, reserved ranges and
  IPv4-mapped forms refused, `allowed_cidrs` excepted) and the connection is
  pinned to the address checked. Responses are capped at
  `max_response_bytes`. Refusals throw `Signing\UrlNotAllowed`.
- `Pdf::signDetached($fileOrBytes)` / `Pdf::verifyDetached($fileOrBytes,
  $signature)` — a detached CAdES `.p7s` with the configured key (and
  timestamp authority), and its check against the configured trust anchors.
- `Pdf::urlPolicy()`.

### Changed
- Requires `dskripchenko/php-pdf` ^1.16.

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
