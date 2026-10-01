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
AcroForm, PDF/A, шифрование, подпись PKCS#7, чтение и слияние готовых PDF.
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
