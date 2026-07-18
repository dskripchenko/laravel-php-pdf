# dskripchenko/laravel-php-pdf

> Laravel bridge for [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf) —
> the pure-PHP, **MIT-licensed** PDF toolkit (generate, read, merge).
> No GPL friction, [faster than mpdf/dompdf](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/BENCHMARKS.md),
> and [conformance-validated on every push](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/CONFORMANCE.md).

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

The macro accepts a `PendingPdf`, a php-pdf `Document` (either layer), or
raw bytes — the mpdf `Output('', 'D')` habit, the Laravel way:

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
AcroForm fields, PDF/A, encryption, PKCS#7 signing, and reading/merging
existing PDFs. See the [php-pdf documentation](https://github.com/dskripchenko/php-pdf#documentation).

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
```

## Migrating from laravel-mpdf / barryvdh wrappers

The underlying toolkit ships compat facades and guides for
[mpdf](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/MIGRATION-FROM-MPDF.md)
and [FPDI](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/MIGRATION-FROM-FPDI.md)
call sites.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- Extensions: `mbstring`, `zlib`, `dom` (plus `openssl` for encryption/signing)

## Testing

```bash
composer test
```

## License

MIT. The underlying `dskripchenko/php-pdf` is MIT as well — no GPL
obligations anywhere in the stack.
