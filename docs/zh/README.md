# dskripchenko/laravel-php-pdf

> [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf) 的 Laravel 桥接包 ——
> 一个纯 PHP、**MIT 许可**的 PDF 工具箱（生成、读取、合并）。没有 GPL 带来的麻烦，
> [比 mpdf/dompdf 更快](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/BENCHMARKS.md)，
> 并且[每次推送都会做规范符合性校验](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/CONFORMANCE.md)。

> 🌐 [English](../../README.md) · [Deutsch](../de/README.md) · [Русский](../ru/README.md) · **中文**

[![Tests](https://img.shields.io/github/actions/workflow/status/dskripchenko/laravel-php-pdf/tests.yml?branch=main&label=tests&logo=github)](https://github.com/dskripchenko/laravel-php-pdf/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/dskripchenko/laravel-php-pdf?logo=packagist&logoColor=white)](https://packagist.org/packages/dskripchenko/laravel-php-pdf)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-red.svg)](https://laravel.com)

## 安装

```bash
composer require dskripchenko/laravel-php-pdf
```

服务提供者与 `Pdf` 门面会自动注册（package discovery）。如需修改配置，可以发布它：

```bash
php artisan vendor:publish --tag=php-pdf-config
```

## 用法

### HTML → PDF

```php
use Dskripchenko\LaravelPhpPdf\Facades\Pdf;

// 字节
$bytes = Pdf::fromHtml('<h1>发票 #1234</h1>')->bytes();

// 文件
Pdf::fromHtml(view('invoices.show', $data)->render())->save(storage_path('invoice.pdf'));

// HTTP 响应
Route::get('/invoice', fn () => Pdf::fromHtml($html)->inline('invoice.pdf'));
Route::get('/invoice/download', fn () => Pdf::fromHtml($html)->download('invoice.pdf'));

// 流式响应——直接渲染进输出缓冲，不会在内存中拼成一整个字符串。
// 适合非常大的文档。
Route::get('/report', fn () => Pdf::fromHtml($html)->stream('report.pdf'));
Route::get('/report/download', fn () => Pdf::fromHtml($html)->streamDownload('report.pdf'));
```

自 php-pdf 1.19 起，HTML 可以包含 `<style>` 块——层叠、选择器、`@media print`。
页面尺寸、方向和页边距取自 `config/php-pdf.php`：`Pdf::fromHtml()` 不会应用
HTML 中的 `@page` 规则。若要由 `@page` 决定，请用 php-pdf 解析 HTML 并渲染结果：
`Pdf::render(\Dskripchenko\PhpPdf\Document::fromHtml($html))`
（配置的字体会生效，默认元数据不会）。

### `response()->pdf()`

这个宏接受 `PendingPdf`、php-pdf 的 `Document`（任一层）、已填写的表单（`Pdf::fillForm()`）或原始字节——
相当于 mpdf 里 `Output('', 'D')` 的习惯写法，只是更符合 Laravel 的风格：

```php
return response()->pdf(Pdf::fromHtml($html), 'invoice.pdf');                // 浏览器内打开
return response()->pdf(Pdf::fromHtml($html), 'invoice.pdf', inline: false); // 下载
```

### 用代码构建文档

```php
$document = Pdf::builder()
    ->heading(1, '季度报告')
    ->paragraph('第一季度营收比预测高出 12%。')
    ->build();

return Pdf::render($document)->download('report.pdf');
```

底层工具箱的能力都可以直接使用：图表、条码、AcroForm 表单域、PDF/A、加密、
数字签名，以及读取与合并已有 PDF。详见
[php-pdf 文档](https://github.com/dskripchenko/php-pdf#documentation)。

### 填写已有表单

`Pdf::fillForm()` 打开在别处生成的 PDF 表单（文件路径或 PDF 字节），并返回 php-pdf 的 `ExistingFormFiller`。对于表单自带字体无法显示的值，会使用配置中的 `fonts.default`，因此西里尔文或中日韩文字无需额外代码即可正常显示：

```php
$form = Pdf::fillForm(storage_path('templates/application.pdf'))
    ->setValues([
        'full_name' => '张伟',
        'agree' => true,
    ])
    ->stampImage(0, storage_path('signature.png'), x: 400, y: 700, width: 120, height: 40)
    ->flatten();

return response()->pdf($form, 'application.pdf', inline: false);
```

`fields()` 列出模板中的字段及其类型和可接受的选项；值的规则以及 `flatten()` 的行为见[填写已有表单](https://github.com/dskripchenko/php-pdf/blob/main/docs/zh/USAGE.md#填写已有表单acroform)。

### 数字签名

先一次性配置密钥（`php-pdf.signing`，见下文），然后：

```php
// 对生成的文档签名。其中的 SignatureField 会被签名，
// 签章外观由证书填充；否则为不可见签名。
return Pdf::fromHtml($html)->signed(['reason' => 'Approved'])->download('contract.pdf');

// 对已有 PDF 签名——包括已签过名的；先前的签名仍然有效。
$signed = Pdf::sign(storage_path('contract.pdf'), ['reason' => 'Countersigned']);

// 认证以允许填写表单、锁定字段、对具名字段签名……
$signed = Pdf::sign(storage_path('contract.pdf'), ['certification' => 2, 'lock' => ['amount'], 'field' => 'approver']);

// ……或直接使用 php-pdf 的 PdfSigner：可见签名域、为后续签署人预留的签名域、
// 位于用户浏览器中的密钥（CryptoPro 浏览器插件）。
// （ExternalSigner 即 Dskripchenko\PhpPdf\Pdf\Signature\External\ExternalSigner。）
$prepared = Pdf::signer($pdf)->prepare(Pdf::signatureConfig(['signer' => new ExternalSigner($userCert)]));

// 填写已签名的表单而不破坏签名。
$filled = Pdf::fillForm($signed)->setValue('amount', '100')->toBytes(incremental: true);

// 长期验证：DSS（B-LT）加文档时间戳（B-LTA）。
$archived = Pdf::ltv($signed);

// 依据已配置的信任锚校验签名。
$report = Pdf::validate($archived);
$report->allValid();

// 文件旁的分离式 .p7s（CAdES）及其验证。
$p7s = Pdf::signDetached(storage_path('invoice.xml'));
Pdf::verifyDetached(storage_path('invoice.xml'), $p7s)->isValid();
```

签名默认为 PAdES（`ETSI.CAdES.detached`）；支持 RSA、ECDSA 与
GOST R 34.10-2012 密钥（GOST 需要将 OpenSSL 的 gost 引擎加载到 PHP 中）。
时间戳服务、OCSP 响应方与 CRL 都通过 Laravel 的 HTTP 客户端访问，因此代理设置生效，
测试中也可使用 `Http::fake()`。
完整功能见[签名指南](https://github.com/dskripchenko/php-pdf/blob/main/docs/zh/SIGNING.md)。

### PHP 之外的密钥：CryptoPro、HSM、云 KMS

`Pdf::sign()`、`->signed()` 与 `Pdf::signatureConfig()` 的 `'signer'` 可接受任意
php-pdf 签名器；此时不再需要配置的密钥（配置的时间戳服务、格式与签名原因仍然生效）。
现成的签名器：

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

若要处处使用它签名，请在容器中绑定它，并在 `php-pdf.signing.signer` 中写上该绑定名——
此时它取代配置的证书与密钥，且只解析一次：

```php
// AppServiceProvider::register()
$this->app->singleton('pdf.signer', fn () => Pkcs11Signer::open(config('services.hsm.module'), pin: config('services.hsm.pin'), keyLabel: 'pdf'));

// config/php-pdf.php → 'signing' => ['signer' => 'pdf.signer', …]   (or PHP_PDF_SIGNER=pdf.signer)
$signed = Pdf::sign(storage_path('contract.pdf'));
```

`Pdf::signDetached()` 同样接受 `'signer'` 以及配置的签名器。CryptoPro 签名器
（即 `CmsSigner`，它返回完整的 CMS）在此需要 php-pdf 1.20 或更高版本。

当这些 URL 来自用户或上传的证书时，请开启
`signing.network.block_private_networks`：主机解析到的每个地址都必须是公网地址
（不允许回环、私有、链路本地及云元数据地址段，`allowed_cidrs` 中列出的除外），
重定向同样受检，连接直接使用已检查的地址，响应大小受 `max_response_bytes` 限制。

## 配置

`config/php-pdf.php` 控制页面默认值、字体、默认元数据、签名与验证（节选）：

```php
'paper' => env('PHP_PDF_PAPER', 'a4'),             // a3..a6, letter, legal, tabloid, executive
'orientation' => env('PHP_PDF_ORIENTATION', 'portrait'),
'margins' => ['top' => null, 'right' => null, 'bottom' => null, 'left' => null], // 单位 pt

'fonts' => [
    // 需要嵌入的 TTF（西里尔、希腊、阿拉伯、CJK 必备——内置的 14 款
    // 标准字体只覆盖拉丁字母）。字体会自动做子集化。
    'default' => storage_path('fonts/DejaVuSans.ttf'),
    'bold' => storage_path('fonts/DejaVuSans-Bold.ttf'),
    // 供 CSS font-family 与 RunStyle(fontFamily: ...) 使用的具名字族：
    // 未配置的字形回退到常规字形。
    'families' => [
        'mono' => ['regular' => storage_path('fonts/DejaVuSansMono.ttf'), 'bold' => storage_path('fonts/DejaVuSansMono-Bold.ttf')],
    ],
    // 用于其他任意字族的 FontProvider（容器绑定或类）。
    'provider' => null,
],

'metadata' => ['Author' => 'ACME Corp.'],  // /Info 的默认条目

'signing' => [
    // 在容器中绑定的 Signer / CmsSigner（CryptoPro、PKCS#11、云 KMS）……
    'signer' => env('PHP_PDF_SIGNER'),
    // ……或 PEM 证书 + 密钥（文件路径或 PEM 文本）……
    'certificate' => env('PHP_PDF_SIGN_CERT'),
    'key' => env('PHP_PDF_SIGN_KEY'),
    'passphrase' => env('PHP_PDF_SIGN_PASSPHRASE'),
    // ……或 PKCS#12 容器。
    'pkcs12' => env('PHP_PDF_SIGN_P12'),
    'pkcs12_password' => env('PHP_PDF_SIGN_P12_PASSWORD'),
    'chain' => [],              // 中间证书
    'format' => env('PHP_PDF_SIGN_FORMAT', 'pades'),  // 或 'pkcs7'（adbe.pkcs7.detached）
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
    'trust' => [],              // Pdf::validate() 使用的根证书
    'system_trust' => false,    // 加入 OpenSSL 的系统根证书
    'fetch_revocation' => false,
],
```

手头没有字体？[dskripchenko/php-pdf-fonts-liberation](https://github.com/dskripchenko/php-pdf-fonts-liberation)
提供 Liberation Sans、Serif 与 Mono（拉丁、西里尔、希腊、希伯来字母），
度量上与 Arial、Times New Roman 和 Courier New 兼容。在 `fonts.provider` 中写上它的
提供器——它负责 `families` 中未列出的字族，包括 `font-family: Arial`——并将默认字体指向它：

```php
'fonts' => [
    'provider' => \Dskripchenko\PhpPdfFontsLiberation\LiberationFontProvider::class,
    'default' => base_path('vendor/dskripchenko/php-pdf-fonts-liberation/assets/fonts/LiberationSans-Regular.ttf'),
    'bold' => base_path('vendor/dskripchenko/php-pdf-fonts-liberation/assets/fonts/LiberationSans-Bold.ttf'),
    'italic' => base_path('vendor/dskripchenko/php-pdf-fonts-liberation/assets/fonts/LiberationSans-Italic.ttf'),
    'bold_italic' => base_path('vendor/dskripchenko/php-pdf-fonts-liberation/assets/fonts/LiberationSans-BoldItalic.ttf'),
],
```

## 从 laravel-mpdf 或 barryvdh 系列封装迁移

底层工具箱提供了兼容门面，以及针对
[mpdf](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/MIGRATION-FROM-MPDF.md)
和 [FPDI](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/MIGRATION-FROM-FPDI.md)
调用点的迁移指南。

## 环境要求

- PHP 8.2+
- Laravel 11、12 或 13
- `dskripchenko/php-pdf` 1.18+（HTML 中使用 `<style>` CSS 需 1.19+）
- 扩展：`mbstring`、`zlib`、`dom`（加密与签名还需 `openssl`）

## 测试

```bash
composer test
```

## php-pdf 家族

| 包 | 新增能力 |
|---|---|
| [php-pdf](https://github.com/dskripchenko/php-pdf) | 核心：HTML（含 CSS）或构建器生成 PDF，读取、合并、填写表单、签名与验证。 |
| **laravel-php-pdf**（本包） | Laravel 桥接：Blade 视图直接生成 PDF、`response()->pdf()`、按配置签名。 |
| [php-pdf-fonts-liberation](https://github.com/dskripchenko/php-pdf-fonts-liberation) | Liberation Sans、Serif 与 Mono，度量上与 Arial、Times New Roman 和 Courier New 兼容（OFL 许可，独立于 MIT 核心之外）。 |
| [php-pdf-cryptopro](https://github.com/dskripchenko/php-pdf-cryptopro) | 使用服务器上 CryptoPro CSP 中的密钥签名：GOST R 34.10-2012，俄罗斯合格电子签名。 |
| [php-pdf-pkcs11](https://github.com/dskripchenko/php-pdf-pkcs11) | 通过 PKCS#11 使用 HSM、智能卡或 USB 令牌上的密钥签名。 |
| [php-pdf-cloud-kms](https://github.com/dskripchenko/php-pdf-cloud-kms) | 使用 AWS KMS、Google Cloud KMS 或 Azure Key Vault 中的密钥签名。 |

## 许可证

MIT。底层的 `dskripchenko/php-pdf` 同样是 MIT——整个技术栈中都不存在 GPL 义务。
