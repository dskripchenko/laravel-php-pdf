# dskripchenko/laravel-php-pdf

> Laravel-Brücke zu [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf) —
> dem reinen PHP-Werkzeugkasten für PDF unter **MIT-Lizenz** (erzeugen, lesen,
> zusammenführen). Keine GPL-Reibung,
> [schneller als mpdf und dompdf](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/BENCHMARKS.md),
> und [bei jedem Push auf Konformität geprüft](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/CONFORMANCE.md).

> 🌐 [English](../../README.md) · **Deutsch** · [Русский](../ru/README.md) · [中文](../zh/README.md)

[![Tests](https://img.shields.io/github/actions/workflow/status/dskripchenko/laravel-php-pdf/tests.yml?branch=main&label=tests&logo=github)](https://github.com/dskripchenko/laravel-php-pdf/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/dskripchenko/laravel-php-pdf?logo=packagist&logoColor=white)](https://packagist.org/packages/dskripchenko/laravel-php-pdf)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-red.svg)](https://laravel.com)

## Installation

```bash
composer require dskripchenko/laravel-php-pdf
```

Service Provider und die `Pdf`-Facade registrieren sich selbst (Package
Discovery). Die Konfiguration lässt sich bei Bedarf veröffentlichen:

```bash
php artisan vendor:publish --tag=php-pdf-config
```

## Verwendung

### HTML → PDF

```php
use Dskripchenko\LaravelPhpPdf\Facades\Pdf;

// Bytes
$bytes = Pdf::fromHtml('<h1>Rechnung #1234</h1>')->bytes();

// Datei
Pdf::fromHtml(view('invoices.show', $data)->render())->save(storage_path('invoice.pdf'));

// HTTP-Antworten
Route::get('/invoice', fn () => Pdf::fromHtml($html)->inline('invoice.pdf'));
Route::get('/invoice/download', fn () => Pdf::fromHtml($html)->download('invoice.pdf'));

// Gestreamte Antworten — direkt in den Ausgabepuffer gerendert, nie als
// ein einziger String im Speicher gehalten. Für sehr große Dokumente.
Route::get('/report', fn () => Pdf::fromHtml($html)->stream('report.pdf'));
Route::get('/report/download', fn () => Pdf::fromHtml($html)->streamDownload('report.pdf'));
```

### `response()->pdf()`

Das Makro nimmt ein `PendingPdf`, ein php-pdf-`Document` (beide Ebenen) oder
rohe Bytes entgegen — die mpdf-Gewohnheit `Output('', 'D')`, nur auf
Laravel-Art:

```php
return response()->pdf(Pdf::fromHtml($html), 'invoice.pdf');                // im Browser
return response()->pdf(Pdf::fromHtml($html), 'invoice.pdf', inline: false); // als Download
```

### Dokumente aus Code

```php
$document = Pdf::builder()
    ->heading(1, 'Quartalsbericht')
    ->paragraph('Der Umsatz im ersten Quartal lag 12 % über der Prognose.')
    ->build();

return Pdf::render($document)->download('report.pdf');
```

Alles aus dem zugrunde liegenden Werkzeugkasten ist erreichbar: Diagramme,
Barcodes, AcroForm-Felder, PDF/A, Verschlüsselung, PKCS#7-Signaturen sowie das
Lesen und Zusammenführen bestehender PDFs. Siehe die
[php-pdf-Dokumentation](https://github.com/dskripchenko/php-pdf#documentation).

## Konfiguration

`config/php-pdf.php` steuert Seitenvorgaben und Schriften:

```php
'paper' => 'a4',              // a3..a6, letter, legal, tabloid, executive
'orientation' => 'portrait',
'margins' => ['top' => null, 'right' => null, 'bottom' => null, 'left' => null], // pt

'fonts' => [
    // Eingebettete TTFs (nötig für Kyrillisch, Griechisch, Arabisch, CJK —
    // die 14 Standardschriften decken nur Latein ab). Schriften werden
    // automatisch auf die benutzten Zeichen reduziert.
    'default' => storage_path('fonts/DejaVuSans.ttf'),
    'bold' => storage_path('fonts/DejaVuSans-Bold.ttf'),
    // Benannte Familien für CSS font-family / RunStyle(fontFamily: ...):
    'families' => [
        'mono' => ['regular' => storage_path('fonts/DejaVuSansMono.ttf')],
    ],
],

'metadata' => ['Author' => 'ACME Corp.'],  // Standardeinträge in /Info
```

## Umstieg von laravel-mpdf und den barryvdh-Wrappern

Der Werkzeugkasten bringt Kompatibilitäts-Facades und Umstiegsleitfäden für
Aufrufe von
[mpdf](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/MIGRATION-FROM-MPDF.md)
und [FPDI](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/MIGRATION-FROM-FPDI.md) mit.

## Voraussetzungen

- PHP 8.2+
- Laravel 11, 12 oder 13
- Erweiterungen: `mbstring`, `zlib`, `dom` (plus `openssl` für Verschlüsselung und Signaturen)

## Tests

```bash
composer test
```

## Lizenz

MIT. Auch das zugrunde liegende `dskripchenko/php-pdf` steht unter MIT — im
gesamten Stack entstehen keine GPL-Pflichten.
