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

Das HTML darf ab php-pdf 1.19 `<style>`-Blöcke enthalten — Kaskade,
Selektoren, `@media print`. Seitengröße, Ausrichtung und Ränder kommen aus
`config/php-pdf.php`: Eine `@page`-Regel im HTML wird von `Pdf::fromHtml()`
nicht angewendet. Soll `@page` entscheiden, das HTML mit php-pdf parsen und
das Ergebnis rendern: `Pdf::render(\Dskripchenko\PhpPdf\Document::fromHtml($html))`
(die konfigurierten Schriften gelten, die Standard-Metadaten nicht).

### HTML-Optionen

Der Abschnitt `html` in `config/php-pdf.php` legt fest, wie `Pdf::fromHtml()`
HTML liest; benannte Argumente überschreiben ihn für einen Aufruf
(php-pdf 1.23+):

```php
// HTML von fremden Autoren: nichts wird von der Festplatte des Servers
// gelesen, nur data:-URLs und die Bildquelle.
Pdf::fromHtml($userHtml, localFiles: false);

// Bilder anhand ihres src von dort, wo die Anwendung sie ablegt.
Pdf::fromHtml($html, imageSource: fn (string $src): ?string =>
    str_starts_with($src, 'storage://') ? Storage::get(substr($src, 10)) : null);

// Ein getaggtes (barrierefreies) PDF auf Deutsch.
Pdf::fromHtml($html, tagged: true, lang: 'de');
```

| Schlüssel | Argument | |
|---|---|---|
| `local_files` (`PHP_PDF_HTML_LOCAL_FILES`) | `localFiles` | `<img src>` und `<link href>` werden von der Festplatte gelesen (Standard true) |
| `image_source` | `imageSource` | eine Container-Bindung oder Klasse eines Callables `(string $src): ?string`, vor `data:`-URLs und lokalen Dateien versucht |
| `element_handlers` | `elementHandlers` | `Html\ElementHandler` (Bindungen oder Klassen) der Reihe nach: was aus einem Element wird, bevor der Parser entscheidet |
| `tagged`, `lang` | `tagged`, `lang` | ein getaggtes PDF und seine Sprache (BCP 47) |
| `base_font_size` | — | die Größe in Punkt, auf die sich `em` und `rem` beziehen (12) |

`Pdf::builder()` beginnt mit der Seite aus der Konfiguration (Format,
Ausrichtung, Ränder), wie `Pdf::fromHtml()`.

### `response()->pdf()`

Das Makro nimmt ein `PendingPdf`, ein php-pdf-`Document` (beide Ebenen), ein
ausgefülltes Formular (`Pdf::fillForm()`) oder rohe Bytes entgegen — die mpdf-Gewohnheit `Output('', 'D')`, nur auf
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
Barcodes, AcroForm-Felder, PDF/A, Verschlüsselung, digitale Signaturen sowie das
Lesen und Zusammenführen bestehender PDFs. Siehe die
[php-pdf-Dokumentation](https://github.com/dskripchenko/php-pdf#documentation).

### Bestehende Formulare ausfüllen

`Pdf::fillForm()` öffnet ein anderswo erzeugtes PDF-Formular — Dateipfad
oder PDF-Bytes — und liefert php-pdfs `ExistingFormFiller`. Für Werte, die
die formulareigenen Schriften nicht darstellen können, wird das
konfigurierte `fonts.default` verwendet, sodass kyrillische oder CJK-Werte
ohne zusätzlichen Code funktionieren:

```php
$form = Pdf::fillForm(storage_path('templates/antrag.pdf'))
    ->setValues([
        'full_name' => 'Erika Mustermann',
        'agree' => true,
    ])
    ->stampImage(0, storage_path('signature.png'), x: 400, y: 700, width: 120, height: 40)
    ->flatten();

return response()->pdf($form, 'antrag.pdf', inline: false);
```

`fields()` listet die Felder der Vorlage mit Typen und zulässigen Optionen;
Wertregeln und das Verhalten von `flatten()` stehen unter
[bestehendes Formular ausfüllen](https://github.com/dskripchenko/php-pdf/blob/main/docs/de/USAGE.md#ein-bestehendes-formular-ausfüllen-acroform).

### Digitale Signaturen

Den Schlüssel einmal konfigurieren (`php-pdf.signing`, unten), dann:

```php
// Ein erzeugtes Dokument signieren. Ein darin enthaltenes SignatureField wird
// signiert, sein Stempel wird aus dem Zertifikat befüllt; sonst ist die Signatur unsichtbar.
return Pdf::fromHtml($html)->signed(['reason' => 'Approved'])->download('contract.pdf');

// Ein bestehendes PDF signieren — auch ein bereits signiertes; frühere Signaturen bleiben gültig.
$signed = Pdf::sign(storage_path('contract.pdf'), ['reason' => 'Countersigned']);

// Für das Ausfüllen von Formularen zertifizieren, Felder sperren, ein benanntes Feld signieren…
$signed = Pdf::sign(storage_path('contract.pdf'), ['certification' => 2, 'lock' => ['amount'], 'field' => 'approver']);

// …oder PdfSigner von php-pdf direkt nutzen: sichtbare Felder, Felder für spätere
// Unterzeichner, ein Schlüssel im Browser des Nutzers (CryptoPro-Browser-Plug-in).
// (ExternalSigner ist Dskripchenko\PhpPdf\Pdf\Signature\External\ExternalSigner.)
$prepared = Pdf::signer($pdf)->prepare(Pdf::signatureConfig(['signer' => new ExternalSigner($userCert)]));

// Ein signiertes Formular ausfüllen, ohne die Signatur zu brechen.
$filled = Pdf::fillForm($signed)->setValue('amount', '100')->toBytes(incremental: true);

// Langzeitvalidierung: DSS (B-LT) plus Dokument-Zeitstempel (B-LTA).
$archived = Pdf::ltv($signed);

// Signaturen gegen die konfigurierten Vertrauensanker prüfen.
$report = Pdf::validate($archived);
$report->allValid();

// Eine abgetrennte .p7s (CAdES) neben einer Datei und ihre Prüfung.
$p7s = Pdf::signDetached(storage_path('invoice.xml'));
Pdf::verifyDetached(storage_path('invoice.xml'), $p7s)->isValid();
```

Signaturen sind standardmäßig PAdES (`ETSI.CAdES.detached`); RSA-, ECDSA- und
GOST-R-34.10-2012-Schlüssel funktionieren (GOST erfordert, dass die gost-Engine
von OpenSSL in PHP geladen ist). Zeitstempeldienste, OCSP-Responder und CRLs werden
über den HTTP-Client von Laravel angesprochen, daher greifen Proxys und `Http::fake()`
funktioniert in Tests.
Der vollständige Funktionsumfang steht im [Signatur-Leitfaden](https://github.com/dskripchenko/php-pdf/blob/main/docs/de/SIGNING.md).

### Schlüssel außerhalb von PHP: CryptoPro, HSM, Cloud-KMS

`Pdf::sign()`, `->signed()` und `Pdf::signatureConfig()` nehmen unter
`'signer'` jeden php-pdf-Signer entgegen; der konfigurierte Schlüssel wird dann
nicht gebraucht (konfigurierter Zeitstempeldienst, Format und Grund gelten
weiterhin). Fertige Signer:

```php
use Dskripchenko\PhpPdfCryptoPro\CryptoProCadesSigner;   // dskripchenko/php-pdf-cryptopro
use Dskripchenko\PhpPdfPkcs11\Pkcs11Signer;              // dskripchenko/php-pdf-pkcs11
use Dskripchenko\PhpPdfCloudKms\Aws\AwsKmsSigner;        // dskripchenko/php-pdf-cloud-kms

// CryptoPro CSP on the server — GOST R 34.10-2012.
$signed = Pdf::sign(storage_path('contract.pdf'), ['signer' => CryptoProCadesSigner::fromThumbprint($thumbprint, pin: $pin)]);

// An HSM, a smart card or a token through PKCS#11.
$signed = Pdf::sign(storage_path('contract.pdf'), ['signer' => Pkcs11Signer::open($module, pin: $pin, keyLabel: 'pdf')]);

// AWS KMS (Google Cloud KMS and Azure Key Vault likewise).
return Pdf::fromHtml($html)
    ->signed(['signer' => AwsKmsSigner::create('alias/pdf-signing', $certificatePem, region: 'eu-central-1')])
    ->download('contract.pdf');
```

Um überall damit zu signieren, den Signer im Container binden und die Bindung
in `php-pdf.signing.signer` nennen — er ersetzt dann das konfigurierte
Zertifikat samt Schlüssel und wird einmal aufgelöst:

```php
// AppServiceProvider::register()
$this->app->singleton('pdf.signer', fn () => Pkcs11Signer::open(config('services.hsm.module'), pin: config('services.hsm.pin'), keyLabel: 'pdf'));

// config/php-pdf.php → 'signing' => ['signer' => 'pdf.signer', …]   (or PHP_PDF_SIGNER=pdf.signer)
$signed = Pdf::sign(storage_path('contract.pdf'));
```

`Pdf::signDetached()` nimmt `'signer'` und den konfigurierten Signer
ebenfalls. Ein CryptoPro-Signer (ein `CmsSigner`, er liefert ein vollständiges
CMS) braucht dort php-pdf 1.20 oder neuer.

Kommen diese URLs von Nutzern oder aus hochgeladenen Zertifikaten,
`signing.network.block_private_networks` einschalten: Jede Adresse, zu der
ein Host aufgelöst wird, muss dann öffentlich sein (kein Loopback, keine
privaten, Link-Local- oder Metadaten-Bereiche, außer sie stehen in
`allowed_cidrs`), Weiterleitungen werden ebenso geprüft, verbunden wird mit
der geprüften Adresse, Antworten sind auf `max_response_bytes` begrenzt.

## Konfiguration

`config/php-pdf.php` steuert Seitenvorgaben, Schriften, Standard-Metadaten,
Signatur und Validierung (ein Auszug):

```php
'paper' => env('PHP_PDF_PAPER', 'a4'),             // a3..a6, letter, legal, tabloid, executive
'orientation' => env('PHP_PDF_ORIENTATION', 'portrait'),
'margins' => ['top' => null, 'right' => null, 'bottom' => null, 'left' => null], // pt

'fonts' => [
    // Eingebettete TTFs (nötig für Kyrillisch, Griechisch, Arabisch, CJK —
    // die 14 Standardschriften decken nur Latein ab). Schriften werden
    // automatisch auf die benutzten Zeichen reduziert.
    'default' => storage_path('fonts/DejaVuSans.ttf'),
    'bold' => storage_path('fonts/DejaVuSans-Bold.ttf'),
    // Benannte Familien für CSS font-family / RunStyle(fontFamily: ...):
    // Ein nicht konfigurierter Schnitt fällt auf den regulären zurück.
    'families' => [
        'mono' => ['regular' => storage_path('fonts/DejaVuSansMono.ttf'), 'bold' => storage_path('fonts/DejaVuSansMono-Bold.ttf')],
    ],
    // Ein FontProvider (Container-Bindung oder Klasse) für jede andere Familie.
    'provider' => null,
],

'metadata' => ['Author' => 'ACME Corp.'],  // Standardeinträge in /Info

'signing' => [
    // Ein im Container gebundener Signer / CmsSigner (CryptoPro, PKCS#11, Cloud-KMS)…
    'signer' => env('PHP_PDF_SIGNER'),
    // …oder PEM-Zertifikat + Schlüssel (Dateipfade oder PEM-Text)…
    'certificate' => env('PHP_PDF_SIGN_CERT'),
    'key' => env('PHP_PDF_SIGN_KEY'),
    'passphrase' => env('PHP_PDF_SIGN_PASSPHRASE'),
    // …oder ein PKCS#12-Container.
    'pkcs12' => env('PHP_PDF_SIGN_P12'),
    'pkcs12_password' => env('PHP_PDF_SIGN_P12_PASSWORD'),
    'chain' => [],              // Zwischenzertifikate
    'format' => env('PHP_PDF_SIGN_FORMAT', 'pades'),  // oder 'pkcs7' (adbe.pkcs7.detached)
    'reason' => null, 'location' => null, 'contact' => null, 'signer_name' => null,
    'timestamp' => [
        'url' => env('PHP_PDF_TSA_URL'),
        'username' => env('PHP_PDF_TSA_USERNAME'), 'password' => env('PHP_PDF_TSA_PASSWORD'),
        'policy' => null, 'headers' => [], 'timeout' => 15,
    ],
    'network' => [
        'block_private_networks' => env('PHP_PDF_SIGN_BLOCK_PRIVATE', false),
        'allowed_cidrs' => [], 'max_response_bytes' => 20 * 1024 * 1024, 'max_redirects' => 3,
    ],
],

'validation' => [
    'trust' => [],              // Wurzelzertifikate für Pdf::validate()
    'system_trust' => false,    // OpenSSL-Systemwurzeln hinzufügen
    'fetch_revocation' => false,
],
```

Keine Schriften zur Hand? [dskripchenko/php-pdf-fonts-liberation](https://github.com/dskripchenko/php-pdf-fonts-liberation)
liefert Liberation Sans, Serif und Mono (Latein, Kyrillisch, Griechisch,
Hebräisch), metrisch kompatibel mit Arial, Times New Roman und Courier New.
Seinen Provider in `fonts.provider` eintragen — er übernimmt die Familien,
die nicht in `families` stehen, `font-family: Arial` eingeschlossen — und die
Standardschrift darauf richten:

```php
'fonts' => [
    'provider' => \Dskripchenko\PhpPdfFontsLiberation\LiberationFontProvider::class,
    'default' => base_path('vendor/dskripchenko/php-pdf-fonts-liberation/assets/fonts/LiberationSans-Regular.ttf'),
    'bold' => base_path('vendor/dskripchenko/php-pdf-fonts-liberation/assets/fonts/LiberationSans-Bold.ttf'),
    'italic' => base_path('vendor/dskripchenko/php-pdf-fonts-liberation/assets/fonts/LiberationSans-Italic.ttf'),
    'bold_italic' => base_path('vendor/dskripchenko/php-pdf-fonts-liberation/assets/fonts/LiberationSans-BoldItalic.ttf'),
],
```

## Umstieg von laravel-mpdf und den barryvdh-Wrappern

Der Werkzeugkasten bringt Kompatibilitäts-Facades und Umstiegsleitfäden für
Aufrufe von
[mpdf](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/MIGRATION-FROM-MPDF.md)
und [FPDI](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/MIGRATION-FROM-FPDI.md) mit.

## Voraussetzungen

- PHP 8.2+
- Laravel 11, 12 oder 13
- `dskripchenko/php-pdf` 1.23+
- Erweiterungen: `mbstring`, `zlib`, `dom` (plus `openssl` für Verschlüsselung und Signaturen)

## Tests

```bash
composer test
```

## Die php-pdf-Familie

| Paket | Was es hinzufügt |
|---|---|
| [php-pdf](https://github.com/dskripchenko/php-pdf) | Der Kern: HTML mit CSS oder ein Builder zu PDF, Lesen, Zusammenführen, Formulare ausfüllen, Signieren und Validieren. |
| **laravel-php-pdf** (dieses Paket) | Laravel-Brücke: eine Blade-View direkt als PDF, `response()->pdf()`, Signieren per Konfiguration. |
| [php-pdf-fonts-liberation](https://github.com/dskripchenko/php-pdf-fonts-liberation) | Liberation Sans, Serif und Mono, metrisch kompatibel mit Arial, Times New Roman und Courier New (OFL, aus dem MIT-Kern herausgehalten). |
| [php-pdf-cryptopro](https://github.com/dskripchenko/php-pdf-cryptopro) | Signiert mit einem Schlüssel in CryptoPro CSP auf dem Server: GOST R 34.10-2012, russische qualifizierte Signaturen. |
| [php-pdf-pkcs11](https://github.com/dskripchenko/php-pdf-pkcs11) | Signiert mit einem Schlüssel auf einem HSM, einer Smartcard oder einem USB-Token über PKCS#11. |
| [php-pdf-cloud-kms](https://github.com/dskripchenko/php-pdf-cloud-kms) | Signiert mit einem Schlüssel in AWS KMS, Google Cloud KMS oder Azure Key Vault. |

## Lizenz

MIT. Auch das zugrunde liegende `dskripchenko/php-pdf` steht unter MIT — im
gesamten Stack entstehen keine GPL-Pflichten.
