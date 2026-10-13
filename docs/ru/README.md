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
$signed = Pdf::sign($pdf, ['certification' => 2, 'lock' => ['amount'], 'field' => 'approver']);

// …или работать с PdfSigner из php-pdf напрямую: видимые поля, поля для последующих
// подписантов, ключ вне сервера (плагин КриптоПро, HSM).
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

Если эти адреса приходят от пользователей или из загруженных
сертификатов, включите `signing.network.block_private_networks`: тогда
каждый адрес, в который разрешается хост, обязан быть публичным (никаких
loopback, частных, link-local диапазонов и адресов метаданных облака, кроме
перечисленных в `allowed_cidrs`), редиректы проверяются так же, соединение
идёт на проверенный адрес, а ответ ограничен `max_response_bytes`.

## Настройка

`config/php-pdf.php` задаёт умолчания страницы и шрифты:

```php
'paper' => 'a4',              // a3..a6, letter, legal, tabloid, executive
'orientation' => 'portrait',
'margins' => ['top' => null, 'right' => null, 'bottom' => null, 'left' => null], // пункты

'fonts' => [
    // Встраиваемые TTF (обязательны для кириллицы, греческого, арабского,
    // CJK — базовые 14 шрифтов покрывают только латиницу). Подмножество
    // шрифта вырезается автоматически.
    'default' => storage_path('fonts/DejaVuSans.ttf'),
    'bold' => storage_path('fonts/DejaVuSans-Bold.ttf'),
    // Именованные семейства для CSS font-family и RunStyle(fontFamily: ...):
    'families' => [
        'mono' => ['regular' => storage_path('fonts/DejaVuSansMono.ttf')],
    ],
],

'metadata' => ['Author' => 'ACME Corp.'],  // записи /Info по умолчанию

'signing' => [
    // PEM-сертификат + ключ (пути к файлам или PEM-текст)…
    'certificate' => env('PHP_PDF_SIGN_CERT'),
    'key' => env('PHP_PDF_SIGN_KEY'),
    'passphrase' => env('PHP_PDF_SIGN_PASSPHRASE'),
    // …или контейнер PKCS#12.
    'pkcs12' => env('PHP_PDF_SIGN_P12'),
    'pkcs12_password' => env('PHP_PDF_SIGN_P12_PASSWORD'),
    'chain' => [],              // промежуточные сертификаты
    'format' => 'pades',        // или 'pkcs7' (adbe.pkcs7.detached)
    'reason' => null, 'location' => null, 'contact' => null, 'signer_name' => null,
    'timestamp' => ['url' => env('PHP_PDF_TSA_URL'), 'username' => null, 'password' => null],
    'network' => ['block_private_networks' => false, 'allowed_cidrs' => [], 'max_response_bytes' => 20 * 1024 * 1024],
],

'validation' => [
    'trust' => [],              // корневые сертификаты для Pdf::validate()
    'system_trust' => false,    // добавить системные корни OpenSSL
    'fetch_revocation' => false,
],
```

## Переход с laravel-mpdf и обёрток barryvdh

В базовом наборе есть совместимые фасады и руководства по переходу для вызовов
[mpdf](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/MIGRATION-FROM-MPDF.md)
и [FPDI](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/MIGRATION-FROM-FPDI.md).

## Требования

- PHP 8.2+
- Laravel 11, 12 или 13
- Расширения: `mbstring`, `zlib`, `dom` (плюс `openssl` для шифрования и подписи)

## Тесты

```bash
composer test
```

## Лицензия

MIT. Базовый `dskripchenko/php-pdf` — тоже MIT: обязательств GPL нигде в стеке нет.
