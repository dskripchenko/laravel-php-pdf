<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf;

use Dskripchenko\PhpPdf\Build\DocumentBuilder;
use Dskripchenko\PhpPdf\Document;
use Dskripchenko\PhpPdf\Layout\Engine;
use Dskripchenko\PhpPdf\Pdf\PdfFont;
use Dskripchenko\PhpPdf\Font\Ttf\TtfFile;
use Dskripchenko\PhpPdf\Section;
use Dskripchenko\PhpPdf\Style\Orientation;
use Dskripchenko\PhpPdf\Style\PageMargins;
use Dskripchenko\PhpPdf\Style\PageSetup;
use Dskripchenko\PhpPdf\Style\PaperSize;

/**
 * Entry point behind the Pdf facade. Turns the php-pdf config file into a
 * ready Engine (fonts) and Section template (paper, margins), so
 * application code deals with content only.
 */
class PdfFactory
{
    private ?Engine $engine = null;

    private bool $engineBuilt = false;

    /**
     * @param  array<string, mixed>  $config  The `php-pdf` config array.
     */
    public function __construct(private readonly array $config = []) {}

    /**
     * Parse HTML into a renderable document.
     *
     * @param  array<string, string>  $metadata
     */
    public function fromHtml(string $html, array $metadata = []): PendingPdf
    {
        $document = Document::fromHtml(
            $html,
            metadata: $metadata + $this->defaultMetadata(),
            sectionTemplate: new Section(pageSetup: $this->pageSetup()),
        );

        return new PendingPdf($document, $this->engine());
    }

    /**
     * Fluent builder for programmatic documents (headings, tables, charts,
     * barcodes...). Wrap the result with {@see render()} to apply the
     * configured fonts.
     */
    public function builder(): DocumentBuilder
    {
        return DocumentBuilder::new();
    }

    /**
     * Wrap a native document so it renders with the configured engine.
     */
    public function render(Document $document): PendingPdf
    {
        return new PendingPdf($document, $this->engine());
    }

    /**
     * The Engine assembled from config, or null when no fonts are
     * configured (base-14 defaults apply).
     */
    public function engine(): ?Engine
    {
        if ($this->engineBuilt) {
            return $this->engine;
        }
        $this->engineBuilt = true;

        $fonts = (array) ($this->config['fonts'] ?? []);
        $families = (array) ($fonts['families'] ?? []);
        $hasSingles = ($fonts['default'] ?? null) !== null;

        if (! $hasSingles && $families === []) {
            return $this->engine = null;
        }

        $load = static function (?string $path): ?PdfFont {
            if ($path === null || $path === '') {
                return null;
            }
            if (! is_readable($path)) {
                throw new \InvalidArgumentException("php-pdf font not readable: $path");
            }

            return new PdfFont(TtfFile::fromFile($path));
        };

        return $this->engine = new Engine(
            defaultFont: $load($fonts['default'] ?? null),
            boldFont: $load($fonts['bold'] ?? null),
            italicFont: $load($fonts['italic'] ?? null),
            boldItalicFont: $load($fonts['bold_italic'] ?? null),
            fontProvider: $families === [] ? null : new ConfigFontProvider($families),
        );
    }

    public function pageSetup(): PageSetup
    {
        $paper = match (strtolower((string) ($this->config['paper'] ?? 'a4'))) {
            'a3' => PaperSize::A3,
            'a5' => PaperSize::A5,
            'a6' => PaperSize::A6,
            'letter' => PaperSize::Letter,
            'legal' => PaperSize::Legal,
            'tabloid' => PaperSize::Tabloid,
            'executive' => PaperSize::Executive,
            default => PaperSize::A4,
        };

        $margins = (array) ($this->config['margins'] ?? []);
        $defaults = new PageMargins;

        return new PageSetup(
            paperSize: $paper,
            orientation: strtolower((string) ($this->config['orientation'] ?? 'portrait')) === 'landscape'
                ? Orientation::Landscape
                : Orientation::Portrait,
            margins: new PageMargins(
                topPt: (float) ($margins['top'] ?? $defaults->topPt),
                rightPt: (float) ($margins['right'] ?? $defaults->rightPt),
                bottomPt: (float) ($margins['bottom'] ?? $defaults->bottomPt),
                leftPt: (float) ($margins['left'] ?? $defaults->leftPt),
            ),
        );
    }

    /**
     * @return array<string, string>
     */
    private function defaultMetadata(): array
    {
        return array_filter(
            (array) ($this->config['metadata'] ?? []),
            static fn ($v) => is_string($v) && $v !== '',
        );
    }
}
