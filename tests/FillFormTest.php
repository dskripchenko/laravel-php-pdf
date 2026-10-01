<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf\Tests;

use Dskripchenko\LaravelPhpPdf\Facades\Pdf;
use Dskripchenko\LaravelPhpPdf\PdfFactory;
use Dskripchenko\PhpPdf\Pdf\Document as PdfDocument;
use Dskripchenko\PhpPdf\Pdf\Forms\ExistingFormFiller;
use Dskripchenko\PhpPdf\Pdf\Reader\ReaderDocument;
use PHPUnit\Framework\Attributes\Test;

final class FillFormTest extends TestCase
{
    private function template(): string
    {
        $pdf = PdfDocument::new();
        $page = $pdf->addPage();
        $page->addFormField('text', 'full_name', 72, 700, 250, 20);
        $page->addFormField('checkbox', 'agree', 72, 660, 14, 14);

        return $pdf->toBytes();
    }

    private function fontsDir(): string
    {
        return dirname(__DIR__).'/vendor/dskripchenko/php-pdf-fonts-liberation/assets/fonts';
    }

    #[Test]
    public function fill_form_accepts_pdf_bytes(): void
    {
        $filler = Pdf::fillForm($this->template());

        self::assertInstanceOf(ExistingFormFiller::class, $filler);
        self::assertSame(['full_name', 'agree'], array_keys($filler->fields()));
    }

    #[Test]
    public function fill_form_accepts_a_path(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'laravel-php-pdf-form-');
        file_put_contents($path, $this->template());

        $bytes = Pdf::fillForm($path)->setValue('full_name', 'Jane Roe')->flatten()->toBytes();
        unlink($path);

        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertSame([], ExistingFormFiller::fromBytes($bytes)->fields());
    }

    #[Test]
    public function the_configured_default_font_draws_non_latin_values(): void
    {
        // Serif, so it cannot be mistaken for the Sans picked up automatically.
        config()->set('php-pdf.fonts.default', $this->fontsDir().'/LiberationSerif-Regular.ttf');
        $this->app->forgetInstance(PdfFactory::class);
        Pdf::clearResolvedInstances();

        $bytes = Pdf::fillForm($this->template())->setValue('full_name', 'Иван Петров')->toBytes();

        self::assertStringContainsString('/BaseFont /LiberationSerif', $bytes);
        self::assertSame('Иван Петров', ExistingFormFiller::fromBytes($bytes)->fields()['full_name']->value);
    }

    #[Test]
    public function an_unreadable_configured_font_fails_early(): void
    {
        config()->set('php-pdf.fonts.default', '/nonexistent/font.ttf');
        $this->app->forgetInstance(PdfFactory::class);
        Pdf::clearResolvedInstances();

        $this->expectException(\InvalidArgumentException::class);
        Pdf::fillForm($this->template());
    }

    #[Test]
    public function response_macro_accepts_a_filled_form(): void
    {
        $filler = Pdf::fillForm($this->template())->setValue('agree', true);

        $response = response()->pdf($filler, 'form.pdf', inline: false);

        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename="form.pdf"', $response->headers->get('Content-Disposition'));
        $doc = ReaderDocument::fromBytes((string) $response->getContent());
        self::assertSame(1, $doc->pageCount());
    }
}
