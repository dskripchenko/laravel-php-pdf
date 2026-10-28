<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf\Facades;

use Dskripchenko\LaravelPhpPdf\PdfFactory;
use Dskripchenko\LaravelPhpPdf\PendingPdf;
use Dskripchenko\PhpPdf\Build\DocumentBuilder;
use Dskripchenko\PhpPdf\Document;
use Illuminate\Support\Facades\Facade;

/**
 * @method static PendingPdf fromHtml(string $html, array<string, string> $metadata = [], ?bool $localFiles = null, ?\Closure $imageSource = null, ?array $elementHandlers = null, ?bool $tagged = null, ?string $lang = null)
 * @method static DocumentBuilder builder()
 * @method static PendingPdf render(Document $document)
 * @method static \Dskripchenko\PhpPdf\Pdf\Forms\ExistingFormFiller fillForm(string $pdf, string $password = '')
 * @method static \Dskripchenko\PhpPdf\Layout\Engine|null engine()
 * @method static \Dskripchenko\PhpPdf\Pdf\SignatureConfig signatureConfig(array<string, mixed> $options = [])
 * @method static \Dskripchenko\PhpPdf\Pdf\Signature\Tsa\TsaClient|null timestampAuthority()
 * @method static \Dskripchenko\PhpPdf\Pdf\Signature\PdfSigner signer(string $pdf, string $password = '')
 * @method static string sign(string $pdf, array<string, mixed>|\Dskripchenko\PhpPdf\Pdf\SignatureConfig $options = [], string $password = '')
 * @method static string ltv(string $pdf, bool $archive = true, string $password = '')
 * @method static \Dskripchenko\PhpPdf\Pdf\Signature\Validation\ValidationReport validate(string $pdf, string $password = '')
 * @method static \Dskripchenko\PhpPdf\Pdf\Signature\Validation\TrustStore trustStore()
 * @method static \Dskripchenko\LaravelPhpPdf\Signing\LaravelHttpTransport httpTransport()
 * @method static \Dskripchenko\LaravelPhpPdf\Signing\UrlPolicy urlPolicy()
 * @method static string signDetached(string $content, array<string, mixed> $options = [])
 * @method static \Dskripchenko\PhpPdf\Pdf\Signature\Validation\SignatureReport verifyDetached(string $content, string $signature)
 *
 * @see PdfFactory
 */
class Pdf extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PdfFactory::class;
    }
}
