# dskripchenko/laravel-php-pdf

> Laravel bridge for [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf) —
> the pure-PHP, **MIT-licensed** PDF toolkit (generate, read, merge).
> No GPL friction, [faster than mpdf/dompdf](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/BENCHMARKS.md),
> and [conformance-validated on every push](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/CONFORMANCE.md).

> 🌐 **English** · [Deutsch](docs/de/README.md) · [Русский](docs/ru/README.md) · [中文](docs/zh/README.md)

[![Tests](https://img.shields.io/github/actions/workflow/status/dskripchenko/laravel-php-pdf/tests.yml?branch=main&label=tests&logo=github)](https://github.com/dskripchenko/laravel-php-pdf/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/dskripchenko/laravel-php-pdf?logo=packagist&logoColor=white)](https://packagist.org/packages/dskripchenko/laravel-php-pdf)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-red.svg)](https://laravel.com)

## Installation

```bash
composer require dskripchenko/laravel-php-pdf
```

The service provider and the `Pdf` facade register automatically
(package discovery). Optionally publish the config:

```bash
php artisan vendor:publish --tag=php-pdf-config
```

## Usage

### HTML → PDF

```php
use Dskripchenko\LaravelPhpPdf\Facades\Pdf;

// bytes
$bytes = Pdf::fromHtml('<h1>Invoice #1234</h1>')->bytes();

// file
Pdf::fromHtml(view('invoices.show', $data)->render())->save(storage_path('invoice.pdf'));

// HTTP responses
Route::get('/invoice', fn () => Pdf::fromHtml($html)->inline('invoice.pdf'));
Route::get('/invoice/download', fn () => Pdf::fromHtml($html)->download('invoice.pdf'));

// Streamed responses — rendered straight into the output buffer, never
// held in memory as one string. For very large documents.
Route::get('/report', fn () => Pdf::fromHtml($html)->stream('report.pdf'));
Route::get('/report/download', fn () => Pdf::fromHtml($html)->streamDownload('report.pdf'));
```

### `response()->pdf()`

The macro accepts a `PendingPdf`, a php-pdf `Document` (either layer), a
filled form (`Pdf::fillForm()`), or raw bytes — the mpdf `Output('', 'D')` habit, the Laravel way:

```php
return response()->pdf(Pdf::fromHtml($html), 'invoice.pdf');               // inline
return response()->pdf(Pdf::fromHtml($html), 'invoice.pdf', inline: false); // download
```

### Programmatic documents

```php
$document = Pdf::builder()
    ->heading(1, 'Quarterly report')
    ->paragraph('Q1 revenue exceeded the forecast by 12%.')
    ->build();

return Pdf::render($document)->download('report.pdf');
```

Everything from the underlying toolkit is reachable — charts, barcodes,
AcroForm fields, PDF/A, encryption, digital signatures, and reading/merging
existing PDFs. See the [php-pdf documentation](https://github.com/dskripchenko/php-pdf#documentation).

### Filling existing forms

`Pdf::fillForm()` opens a PDF form produced elsewhere — a file path or the
PDF bytes — and returns php-pdf's `ExistingFormFiller`. The configured
`fonts.default` is used for values the form's own fonts cannot show, so
Cyrillic or CJK values work without extra code:

```php
$form = Pdf::fillForm(storage_path('templates/application.pdf'))
    ->setValues([
        'full_name' => 'Иван Петров',
        'agree' => true,
    ])
    ->stampImage(0, storage_path('signature.png'), x: 400, y: 700, width: 120, height: 40)
    ->flatten();

return response()->pdf($form, 'application.pdf', inline: false);
```

`fields()` lists the template's fields with their types and accepted
options; see [filling an existing form](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/USAGE.md#fill-an-existing-form-acroform)
for the value rules and what `flatten()` does.

### Digital signatures

Configure the key once (`php-pdf.signing`, below), then:

```php
// Sign a generated document. A SignatureField in it is signed with its
// stamp filled from the certificate; otherwise the signature is invisible.
return Pdf::fromHtml($html)->signed(['reason' => 'Approved'])->download('contract.pdf');

// Sign an existing PDF — or one signed before; earlier signatures stay valid.
$signed = Pdf::sign(storage_path('contract.pdf'), ['reason' => 'Countersigned']);

// Certify for form filling, lock fields, sign a named field…
$signed = Pdf::sign($pdf, ['certification' => 2, 'lock' => ['amount'], 'field' => 'approver']);

// …or drive php-pdf's PdfSigner directly: visible fields, fields for later
// signers, a key outside the server (CryptoPro plug-in, HSM).
// (ExternalSigner is Dskripchenko\PhpPdf\Pdf\Signature\External\ExternalSigner.)
$prepared = Pdf::signer($pdf)->prepare(Pdf::signatureConfig(['signer' => new ExternalSigner($userCert)]));

// Fill a signed form without breaking the signature.
$filled = Pdf::fillForm($signed)->setValue('amount', '100')->toBytes(incremental: true);

// Long-term validation: DSS (B-LT) plus a document timestamp (B-LTA).
$archived = Pdf::ltv($signed);

// Check signatures against the configured trust anchors.
$report = Pdf::validate($archived);
$report->allValid();

// A detached .p7s (CAdES) beside a file, and its check.
$p7s = Pdf::signDetached(storage_path('invoice.xml'));
Pdf::verifyDetached(storage_path('invoice.xml'), $p7s)->isValid();
```

Signatures are PAdES (`ETSI.CAdES.detached`) by default; RSA, ECDSA and
GOST R 34.10-2012 keys work (GOST needs OpenSSL's gost engine loaded into
PHP). Timestamp authorities, OCSP responders and CRLs are reached through
Laravel's HTTP client, so proxies apply and `Http::fake()` works in tests.
The full feature set is in the [signing guide](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/SIGNING.md).

When those URLs come from users or from uploaded certificates, turn on
`signing.network.block_private_networks`: every address a host resolves to
must then be public (no loopback, private, link-local or metadata ranges,
unless listed in `allowed_cidrs`), redirects are checked the same way, the
connection goes to the address that was checked, and responses are capped
at `max_response_bytes`.

## Configuration

`config/php-pdf.php` controls page defaults and fonts:

```php
'paper' => 'a4',              // a3..a6, letter, legal, tabloid, executive
'orientation' => 'portrait',
'margins' => ['top' => null, 'right' => null, 'bottom' => null, 'left' => null], // pt

'fonts' => [
    // Embedded TTFs (required for Cyrillic, Greek, Arabic, CJK — the
    // base-14 defaults cover Latin only). Fonts are subset automatically.
    'default' => storage_path('fonts/DejaVuSans.ttf'),
    'bold' => storage_path('fonts/DejaVuSans-Bold.ttf'),
    // Named families for CSS font-family / RunStyle(fontFamily: ...):
    'families' => [
        'mono' => ['regular' => storage_path('fonts/DejaVuSansMono.ttf')],
    ],
],

'metadata' => ['Author' => 'ACME Corp.'],  // default /Info entries

'signing' => [
    // PEM certificate + key (file paths or PEM text)…
    'certificate' => env('PHP_PDF_SIGN_CERT'),
    'key' => env('PHP_PDF_SIGN_KEY'),
    'passphrase' => env('PHP_PDF_SIGN_PASSPHRASE'),
    // …or a PKCS#12 container.
    'pkcs12' => env('PHP_PDF_SIGN_P12'),
    'pkcs12_password' => env('PHP_PDF_SIGN_P12_PASSWORD'),
    'chain' => [],              // intermediate certificates
    'format' => 'pades',        // or 'pkcs7' (adbe.pkcs7.detached)
    'reason' => null, 'location' => null, 'contact' => null, 'signer_name' => null,
    'timestamp' => ['url' => env('PHP_PDF_TSA_URL'), 'username' => null, 'password' => null],
    'network' => ['block_private_networks' => false, 'allowed_cidrs' => [], 'max_response_bytes' => 20 * 1024 * 1024],
],

'validation' => [
    'trust' => [],              // root certificates for Pdf::validate()
    'system_trust' => false,    // add OpenSSL's system roots
    'fetch_revocation' => false,
],
```

## Migrating from laravel-mpdf / barryvdh wrappers

The underlying toolkit ships compat facades and guides for
[mpdf](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/MIGRATION-FROM-MPDF.md)
and [FPDI](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/MIGRATION-FROM-FPDI.md)
call sites.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- Extensions: `mbstring`, `zlib`, `dom` (plus `openssl` for encryption and signatures)

## Testing

```bash
composer test
```

## License

MIT. The underlying `dskripchenko/php-pdf` is MIT as well — no GPL
obligations anywhere in the stack.
