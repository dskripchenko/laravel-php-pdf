<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf\Tests;

use Dskripchenko\LaravelPhpPdf\Facades\Pdf;
use Dskripchenko\LaravelPhpPdf\PdfFactory;
use Dskripchenko\PhpPdf\Html\ElementHandler;
use Dskripchenko\PhpPdf\Html\HtmlContext;
use Dskripchenko\PhpPdf\Style\Orientation;
use Dskripchenko\PhpPdf\Style\PaperSize;
use PHPUnit\Framework\Attributes\Test;

final class HtmlOptionsTest extends TestCase
{
    private string $png = '';

    protected function setUp(): void
    {
        parent::setUp();
        $gd = imagecreatetruecolor(8, 8);
        $this->png = (string) tempnam(sys_get_temp_dir(), 'laravel-php-pdf-').'.png';
        imagepng($gd, $this->png);
        CountingHandler::$seen = 0;
    }

    protected function tearDown(): void
    {
        @unlink($this->png);
        parent::tearDown();
    }

    /** @param array<string, mixed> $html */
    private function configure(array $html): void
    {
        config()->set('php-pdf.html', $html);
        $this->app->forgetInstance(PdfFactory::class);
        Pdf::clearResolvedInstances();
    }

    private static function hasImage(string $pdf): bool
    {
        return str_contains($pdf, '/Subtype /Image');
    }

    #[Test]
    public function local_files_are_read_by_default(): void
    {
        $bytes = Pdf::fromHtml('<p><img src="'.$this->png.'"></p>')->bytes();

        self::assertTrue(self::hasImage($bytes));
    }

    #[Test]
    public function local_files_off_in_the_config_reads_nothing_from_the_disk(): void
    {
        $this->configure(['local_files' => false]);

        self::assertFalse(self::hasImage(Pdf::fromHtml('<p><img src="'.$this->png.'"></p>')->bytes()));
        // A call may turn it back on.
        self::assertTrue(self::hasImage(Pdf::fromHtml('<p><img src="'.$this->png.'"></p>', localFiles: true)->bytes()));
    }

    #[Test]
    public function the_configured_image_source_serves_images(): void
    {
        $this->app->instance('test.images', new class($this->png)
        {
            public function __construct(private string $png) {}

            public function __invoke(string $src): ?string
            {
                return $src === 'storage://logo' ? (string) file_get_contents($this->png) : null;
            }
        });
        $this->configure(['local_files' => false, 'image_source' => 'test.images']);

        self::assertTrue(self::hasImage(Pdf::fromHtml('<p><img src="storage://logo"></p>')->bytes()));
        self::assertFalse(self::hasImage(Pdf::fromHtml('<p><img src="storage://other"></p>')->bytes()));
    }

    #[Test]
    public function an_image_source_per_call_wins(): void
    {
        $png = $this->png;
        $bytes = Pdf::fromHtml(
            '<p><img src="db:42"></p>',
            imageSource: fn (string $src): ?string => $src === 'db:42' ? (string) file_get_contents($png) : null,
        )->bytes();

        self::assertTrue(self::hasImage($bytes));
    }

    #[Test]
    public function the_configured_element_handlers_are_consulted(): void
    {
        $this->configure(['element_handlers' => [CountingHandler::class]]);

        Pdf::fromHtml('<p>One</p><p>Two</p>')->bytes();

        self::assertGreaterThan(0, CountingHandler::$seen);
    }

    #[Test]
    public function an_image_source_that_is_not_callable_is_refused(): void
    {
        $this->app->instance('test.not-callable', new \stdClass);
        $this->configure(['image_source' => 'test.not-callable']);

        $this->expectException(\LogicException::class);
        Pdf::fromHtml('<p>x</p>');
    }

    #[Test]
    public function tagged_and_lang_from_the_config_or_the_call(): void
    {
        $this->configure(['tagged' => true, 'lang' => 'ru']);
        $bytes = Pdf::fromHtml('<h1>Отчёт</h1><p>Текст.</p>')->bytes();
        self::assertStringContainsString('/StructTreeRoot', $bytes);
        self::assertStringContainsString('/Lang (ru)', $bytes);

        $untagged = Pdf::fromHtml('<p>x</p>', tagged: false, lang: 'en')->bytes();
        self::assertStringNotContainsString('/StructTreeRoot', $untagged);
        self::assertStringContainsString('/Lang (en)', $untagged);
    }

    #[Test]
    public function the_builder_starts_on_the_page_from_the_config(): void
    {
        config()->set('php-pdf.paper', 'a5');
        config()->set('php-pdf.orientation', 'landscape');
        $this->app->forgetInstance(PdfFactory::class);
        Pdf::clearResolvedInstances();

        $document = Pdf::builder()->paragraph('x')->build();

        self::assertSame(PaperSize::A5, $document->section->pageSetup->paperSize);
        self::assertSame(Orientation::Landscape, $document->section->pageSetup->orientation);
    }
}

final class CountingHandler implements ElementHandler
{
    public static int $seen = 0;

    public function handle(\DOMElement $element, HtmlContext $context): ?array
    {
        self::$seen++;

        return null;
    }
}
