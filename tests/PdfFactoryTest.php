<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf\Tests;

use Dskripchenko\LaravelPhpPdf\Facades\Pdf;
use Dskripchenko\LaravelPhpPdf\PdfFactory;
use Dskripchenko\LaravelPhpPdf\PendingPdf;
use PHPUnit\Framework\Attributes\Test;

final class PdfFactoryTest extends TestCase
{
    #[Test]
    public function factory_is_a_container_singleton_with_alias(): void
    {
        self::assertSame(
            $this->app->make(PdfFactory::class),
            $this->app->make('php-pdf'),
        );
    }

    #[Test]
    public function facade_from_html_produces_pdf_bytes(): void
    {
        $pdf = Pdf::fromHtml('<h1>Facade check</h1><p>Body.</p>');

        self::assertInstanceOf(PendingPdf::class, $pdf);
        self::assertStringStartsWith('%PDF-', $pdf->bytes());
    }

    #[Test]
    public function save_writes_the_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'laravel-php-pdf-');
        $written = Pdf::fromHtml('<p>To disk.</p>')->save($path);

        self::assertGreaterThan(0, $written);
        self::assertStringStartsWith('%PDF-', (string) file_get_contents($path));
        unlink($path);
    }

    #[Test]
    public function per_document_metadata_wins_over_config(): void
    {
        config()->set('php-pdf.metadata', ['Author' => 'Config Author', 'Title' => 'Config Title']);
        $this->refreshFactory();

        $bytes = Pdf::fromHtml('<p>x</p>', metadata: ['Title' => 'Call Title'])->bytes();

        self::assertStringContainsString('(Call Title)', $bytes);
        self::assertStringContainsString('(Config Author)', $bytes);
        self::assertStringNotContainsString('(Config Title)', $bytes);
    }

    #[Test]
    public function paper_and_orientation_config_apply(): void
    {
        config()->set('php-pdf.paper', 'letter');
        config()->set('php-pdf.orientation', 'landscape');
        $this->refreshFactory();

        $bytes = Pdf::fromHtml('<p>x</p>')->bytes();

        self::assertStringContainsString('/MediaBox [0 0 792 612]', $bytes);
    }

    #[Test]
    public function misconfigured_font_path_throws_a_clear_error(): void
    {
        config()->set('php-pdf.fonts.default', '/nonexistent/font.ttf');
        $this->refreshFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('font not readable');
        Pdf::fromHtml('<p>x</p>')->bytes();
    }

    #[Test]
    public function builder_round_trip_renders_through_the_engine(): void
    {
        $document = Pdf::builder()
            ->heading(1, 'Built')
            ->paragraph('By the fluent builder.')
            ->build();

        self::assertStringStartsWith('%PDF-', Pdf::render($document)->bytes());
    }

    private function refreshFactory(): void
    {
        $this->app->forgetInstance(PdfFactory::class);
        $this->app->singleton(PdfFactory::class, fn ($app) => new PdfFactory((array) $app['config']->get('php-pdf', [])));
        $this->app->alias(PdfFactory::class, 'php-pdf');
        Pdf::clearResolvedInstances();
    }
}
