# dskripchenko/laravel-php-pdf

> Мост для Laravel к [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf) —
> набору инструментов для PDF на чистом PHP под **лицензией MIT** (генерация,
> чтение, слияние). Никаких сложностей с GPL,
> [быстрее mpdf и dompdf](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/BENCHMARKS.md),
> [проверка на соответствие спецификации при каждом пуше](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/CONFORMANCE.md).

> 🌐 [English](../../README.md) · [Deutsch](../de/README.md) · **Русский** · [中文](../zh/README.md)

[![Tests](https://img.shields.io/github/actions/workflow/status/dskripchenko/laravel-php-pdf/tests.yml?branch=main&label=tests&logo=github)](https://github.com/dskripchenko/laravel-php-pdf/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/dskripchenko/laravel-php-pdf?logo=packagist&logoColor=white)](https://packagist.org/packages/dskripchenko/laravel-php-pdf)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-red.svg)](https://laravel.com)

## Установка

```bash
composer require dskripchenko/laravel-php-pdf
```

Провайдер и фасад `Pdf` регистрируются автоматически (package discovery).
Конфиг при желании публикуется:

```bash
php artisan vendor:publish --tag=php-pdf-config
```

## Использование

### HTML → PDF

```php
use Dskripchenko\LaravelPhpPdf\Facades\Pdf;

// байты
$bytes = Pdf::fromHtml('<h1>Счёт №1234</h1>')->bytes();

// файл
Pdf::fromHtml(view('invoices.show', $data)->render())->save(storage_path('invoice.pdf'));

// HTTP-ответы
Route::get('/invoice', fn () => Pdf::fromHtml($html)->inline('invoice.pdf'));
Route::get('/invoice/download', fn () => Pdf::fromHtml($html)->download('invoice.pdf'));

// Потоковые ответы — документ рисуется прямо в буфер вывода и никогда
// не собирается в памяти одной строкой. Для очень больших документов.
Route::get('/report', fn () => Pdf::fromHtml($html)->stream('report.pdf'));
Route::get('/report/download', fn () => Pdf::fromHtml($html)->streamDownload('report.pdf'));
```

HTML может содержать блоки `<style>` — каскад, селекторы, `@media print` —
начиная с php-pdf 1.19. Размер страницы, ориентация и поля берутся из
`config/php-pdf.php`: правило `@page` в HTML методом `Pdf::fromHtml()` не
применяется. Чтобы размер задавал `@page`, разберите HTML средствами php-pdf
и отрисуйте результат: `Pdf::render(\Dskripchenko\PhpPdf\Document::fromHtml($html))`
(настроенные шрифты применяются, метаданные по умолчанию — нет).

### `response()->pdf()`

Макрос принимает `PendingPdf`, `Document` из php-pdf (любого слоя),
заполненную форму (`Pdf::fillForm()`) или просто байты — привычка mpdf `Output('', 'D')`, но по-ларавеловски:

```php
return response()->pdf(Pdf::fromHtml($html), 'invoice.pdf');                // в браузере
return response()->pdf(Pdf::fromHtml($html), 'invoice.pdf', inline: false); // скачиванием
```

### Документы из кода

```php
$document = Pdf::builder()
    ->heading(1, 'Квартальный отчёт')
    ->paragraph('Выручка за I квартал превысила прогноз на 12%.')
    ->build();

return Pdf::render($document)->download('report.pdf');
```

Доступно всё, что умеет базовый набор инструментов: графики, штрихкоды, поля
AcroForm, PDF/A, шифрование, цифровая подпись, чтение и слияние готовых PDF.
Смотрите [документацию php-pdf](https://github.com/dskripchenko/php-pdf#documentation).

### Заполнение готовых форм

`Pdf::fillForm()` открывает PDF-форму, сделанную где-то ещё (путь к файлу
или байты PDF), и возвращает `ExistingFormFiller` из php-pdf. Для значений,
которые собственные шрифты формы показать не могут, берётся настроенный
`fonts.default` — кириллица и CJK работают без дополнительного кода:

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

`fields()` перечисляет поля шаблона с типами и допустимыми вариантами;
правила значений и поведение `flatten()` — в разделе
[заполнение существующей формы](https://github.com/dskripchenko/php-pdf/blob/main/docs/ru/USAGE.md#заполнение-существующей-формы-acroform).

### Цифровая подпись

Один раз настройте ключ (`php-pdf.signing`, ниже), затем:

```php
// Подписать сгенерированный документ. Поле SignatureField в нём подписывается,
// а его штамп заполняется из сертификата; иначе подпись невидимая.
return Pdf::fromHtml($html)->signed(['reason' => 'Approved'])->download('contract.pdf');

// Подписать готовый PDF — в том числе уже подписанный; прежние подписи остаются действительными.
$signed = Pdf::sign(storage_path('contract.pdf'), ['reason' => 'Countersigned']);

// Сертифицировать для заполнения форм, заблокировать поля, подписать именованное поле…
$signed = Pdf::sign(storage_path('contract.pdf'), ['certification' => 2, 'lock' => ['amount'], 'field' => 'approver']);

// …или работать с PdfSigner из php-pdf напрямую: видимые поля, поля для последующих
// подписантов, ключ в браузере пользователя (браузерный плагин КриптоПро).
// (ExternalSigner — это Dskripchenko\PhpPdf\Pdf\Signature\External\ExternalSigner.)
$prepared = Pdf::signer($pdf)->prepare(Pdf::signatureConfig(['signer' => new ExternalSigner($userCert)]));

// Заполнить подписанную форму, не нарушив подпись.
$filled = Pdf::fillForm($signed)->setValue('amount', '100')->toBytes(incremental: true);

// Долгосрочная валидация: DSS (B-LT) плюс штамп времени документа (B-LTA).
$archived = Pdf::ltv($signed);

// Проверить подписи по настроенным доверенным корням.
$report = Pdf::validate($archived);
$report->allValid();

// Откреплённая .p7s (CAdES) рядом с файлом и её проверка.
$p7s = Pdf::signDetached(storage_path('invoice.xml'));
Pdf::verifyDetached(storage_path('invoice.xml'), $p7s)->isValid();
```

Подписи по умолчанию — PAdES (`ETSI.CAdES.detached`); работают ключи RSA, ECDSA и
ГОСТ Р 34.10-2012 (для ГОСТ в PHP должен быть загружен движок gost библиотеки
OpenSSL). К службам штампов времени, OCSP-ответчикам и CRL обращаются через
HTTP-клиент Laravel, поэтому прокси применяются, а в тестах работает `Http::fake()`.
Полный набор возможностей — в [руководстве по подписи](https://github.com/dskripchenko/php-pdf/blob/main/docs/ru/SIGNING.md).

### Ключи вне PHP: КриптоПро, HSM, облачные KMS

`Pdf::sign()`, `->signed()` и `Pdf::signatureConfig()` принимают в `'signer'`
любой подписант php-pdf; настроенный ключ тогда не нужен (настроенные служба
штампов времени, формат и причина по-прежнему применяются). Готовые подписанты:

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

Чтобы подписывать им везде, зарегистрируйте его в контейнере и укажите имя
привязки в `php-pdf.signing.signer` — тогда он заменяет настроенные
сертификат и ключ и разрешается один раз:

```php
// AppServiceProvider::register()
$this->app->singleton('pdf.signer', fn () => Pkcs11Signer::open(config('services.hsm.module'), pin: config('services.hsm.pin'), keyLabel: 'pdf'));

// config/php-pdf.php → 'signing' => ['signer' => 'pdf.signer', …]   (or PHP_PDF_SIGNER=pdf.signer)
$signed = Pdf::sign(storage_path('contract.pdf'));
```

`Pdf::signDetached()` тоже принимает `'signer'` и настроенный подписант.
Подписанту КриптоПро (это `CmsSigner`, он возвращает CMS целиком) там нужен
php-pdf 1.20 или новее.

Если эти адреса приходят от пользователей или из загруженных
сертификатов, включите `signing.network.block_private_networks`: тогда
каждый адрес, в который разрешается хост, обязан быть публичным (никаких
loopback, частных, link-local диапазонов и адресов метаданных облака, кроме
перечисленных в `allowed_cidrs`), редиректы проверяются так же, соединение
идёт на проверенный адрес, а ответ ограничен `max_response_bytes`.

## Настройка

`config/php-pdf.php` задаёт умолчания страницы, шрифты, метаданные по
умолчанию, подпись и проверку (фрагмент):

```php
'paper' => env('PHP_PDF_PAPER', 'a4'),             // a3..a6, letter, legal, tabloid, executive
'orientation' => env('PHP_PDF_ORIENTATION', 'portrait'),
'margins' => ['top' => null, 'right' => null, 'bottom' => null, 'left' => null], // пункты

'fonts' => [
    // Встраиваемые TTF (обязательны для кириллицы, греческого, арабского,
    // CJK — базовые 14 шрифтов покрывают только латиницу). Подмножество
    // шрифта вырезается автоматически.
    'default' => storage_path('fonts/DejaVuSans.ttf'),
    'bold' => storage_path('fonts/DejaVuSans-Bold.ttf'),
    // Именованные семейства для CSS font-family и RunStyle(fontFamily: ...):
    // Ненастроенное начертание откатывается к обычному.
    'families' => [
        'mono' => ['regular' => storage_path('fonts/DejaVuSansMono.ttf'), 'bold' => storage_path('fonts/DejaVuSansMono-Bold.ttf')],
    ],
    // FontProvider (привязка в контейнере или класс) для любых других семейств.
    'provider' => null,
],

'metadata' => ['Author' => 'ACME Corp.'],  // записи /Info по умолчанию

'signing' => [
    // Signer / CmsSigner, зарегистрированный в контейнере (КриптоПро, PKCS#11, облачные KMS)…
    'signer' => env('PHP_PDF_SIGNER'),
    // …или PEM-сертификат + ключ (пути к файлам или PEM-текст)…
    'certificate' => env('PHP_PDF_SIGN_CERT'),
    'key' => env('PHP_PDF_SIGN_KEY'),
    'passphrase' => env('PHP_PDF_SIGN_PASSPHRASE'),
    // …или контейнер PKCS#12.
    'pkcs12' => env('PHP_PDF_SIGN_P12'),
    'pkcs12_password' => env('PHP_PDF_SIGN_P12_PASSWORD'),
    'chain' => [],              // промежуточные сертификаты
    'format' => env('PHP_PDF_SIGN_FORMAT', 'pades'),  // или 'pkcs7' (adbe.pkcs7.detached)
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
    'trust' => [],              // корневые сертификаты для Pdf::validate()
    'system_trust' => false,    // добавить системные корни OpenSSL
    'fetch_revocation' => false,
],
```

Нет подходящих шрифтов? [dskripchenko/php-pdf-fonts-liberation](https://github.com/dskripchenko/php-pdf-fonts-liberation)
поставляет Liberation Sans, Serif и Mono (латиница, кириллица, греческий,
иврит), метрически совместимые с Arial, Times New Roman и Courier New.
Укажите его провайдер в `fonts.provider` — он отвечает за семейства, которых
нет в `families`, включая `font-family: Arial`, — а шрифт по умолчанию
направьте на него:

```php
'fonts' => [
    'provider' => \Dskripchenko\PhpPdfFontsLiberation\LiberationFontProvider::class,
    'default' => base_path('vendor/dskripchenko/php-pdf-fonts-liberation/assets/fonts/LiberationSans-Regular.ttf'),
    'bold' => base_path('vendor/dskripchenko/php-pdf-fonts-liberation/assets/fonts/LiberationSans-Bold.ttf'),
    'italic' => base_path('vendor/dskripchenko/php-pdf-fonts-liberation/assets/fonts/LiberationSans-Italic.ttf'),
    'bold_italic' => base_path('vendor/dskripchenko/php-pdf-fonts-liberation/assets/fonts/LiberationSans-BoldItalic.ttf'),
],
```

## Переход с laravel-mpdf и обёрток barryvdh

В базовом наборе есть совместимые фасады и руководства по переходу для вызовов
[mpdf](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/MIGRATION-FROM-MPDF.md)
и [FPDI](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/MIGRATION-FROM-FPDI.md).

## Требования

- PHP 8.2+
- Laravel 11, 12 или 13
- `dskripchenko/php-pdf` 1.18+ (1.19+ для CSS из `<style>` в HTML)
- Расширения: `mbstring`, `zlib`, `dom` (плюс `openssl` для шифрования и подписи)

## Тесты

```bash
composer test
```

## Семейство php-pdf

| Пакет | Что добавляет |
|---|---|
| [php-pdf](https://github.com/dskripchenko/php-pdf) | Ядро: HTML с CSS или билдер → PDF, чтение, слияние, заполнение форм, подпись и проверка. |
| **laravel-php-pdf** (этот пакет) | Мост для Laravel: Blade-шаблон сразу в PDF, `response()->pdf()`, подпись по конфигу. |
| [php-pdf-fonts-liberation](https://github.com/dskripchenko/php-pdf-fonts-liberation) | Liberation Sans, Serif и Mono, метрически совместимые с Arial, Times New Roman и Courier New (OFL, вынесены из MIT-ядра). |
| [php-pdf-cryptopro](https://github.com/dskripchenko/php-pdf-cryptopro) | Подпись ключом в КриптоПро CSP на сервере: ГОСТ Р 34.10-2012, российская КЭП. |
| [php-pdf-pkcs11](https://github.com/dskripchenko/php-pdf-pkcs11) | Подпись ключом на HSM, смарт-карте или USB-токене через PKCS#11. |
| [php-pdf-cloud-kms](https://github.com/dskripchenko/php-pdf-cloud-kms) | Подпись ключом в AWS KMS, Google Cloud KMS или Azure Key Vault. |

## Лицензия

MIT. Базовый `dskripchenko/php-pdf` — тоже MIT: обязательств GPL нигде в стеке нет.
