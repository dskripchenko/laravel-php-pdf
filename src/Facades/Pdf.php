<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf\Facades;

use Dskripchenko\LaravelPhpPdf\PdfFactory;
use Dskripchenko\LaravelPhpPdf\PendingPdf;
use Dskripchenko\PhpPdf\Build\DocumentBuilder;
use Dskripchenko\PhpPdf\Document;
use Illuminate\Support\Facades\Facade;

/**
 * @method static PendingPdf fromHtml(string $html, array<string, string> $metadata = [])
 * @method static DocumentBuilder builder()
 * @method static PendingPdf render(Document $document)
 * @method static \Dskripchenko\PhpPdf\Layout\Engine|null engine()
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
