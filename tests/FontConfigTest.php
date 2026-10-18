<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf\Tests;

use Dskripchenko\LaravelPhpPdf\ConfigFontProvider;
use Dskripchenko\LaravelPhpPdf\Facades\Pdf;
use Dskripchenko\LaravelPhpPdf\PdfFactory;
use Dskripchenko\PhpPdfFontsLiberation\LiberationFontProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Font families with their faces, and a FontProvider from the config.
 */
final class FontConfigTest extends TestCase
{
    private static function font(string $file): string
    {
        return dirname(__DIR__).'/vendor/dskripchenko/php-pdf-fonts-liberation/assets/fonts/'.$file.'.ttf';
    }

    private function refreshFactory(): void
    {
        $this->app->forgetInstance(PdfFactory::class);
        Pdf::clearResolvedInstances();
    }

    #[Test]
    public function a_family_answers_for_each_configured_face(): void
    {
        $provider = new ConfigFontProvider([
            'mono' => ['regular' => self::font('LiberationMono-Regular'), 'bold' => self::font('LiberationMono-Bold')],
        ]);

        self::assertSame('LiberationMono', $provider->resolve('mono')?->postScriptName());
        self::assertSame('LiberationMono', $provider->resolve('Mono-Regular')?->postScriptName());
        self::assertSame('LiberationMono-Bold', $provider->resolve('mono-Bold')?->postScriptName());
        // Not configured: null, so php-pdf falls back to the next face.
        self::assertNull($provider->resolve('mono-Italic'));
        self::assertNull($provider->resolve('mono-BoldItalic'));
        self::assertNull($provider->resolve('serif-Bold'));
    }

    #[Test]
    public function bold_text_in_a_family_uses_the_bold_face(): void
    {
        config()->set('php-pdf.fonts.families', [
            'mono' => ['regular' => self::font('LiberationMono-Regular'), 'bold' => self::font('LiberationMono-Bold')],
        ]);
        $this->refreshFactory();

        $bytes = Pdf::fromHtml('<p style="font-family: mono">plain <b>bold</b></p>')->bytes();

        self::assertMatchesRegularExpression('#/BaseFont /(?:[A-Z]{6}\+)?LiberationMono-Bold(?![\w-])#', $bytes);
        self::assertMatchesRegularExpression('#/BaseFont /(?:[A-Z]{6}\+)?LiberationMono(?![\w-])#', $bytes);
    }

    #[Test]
    public function a_font_provider_from_the_config_answers_for_other_families(): void
    {
        config()->set('php-pdf.fonts.provider', LiberationFontProvider::class);
        $this->refreshFactory();

        $bytes = Pdf::fromHtml('<p style="font-family: Arial">Привет</p>')->bytes();

        self::assertMatchesRegularExpression('#/BaseFont /(?:[A-Z]{6}\+)?LiberationSans(?![\w-])#', $bytes);
    }

    #[Test]
    public function configured_families_come_before_the_provider(): void
    {
        config()->set('php-pdf.fonts.families', ['Arial' => ['regular' => self::font('LiberationSerif-Regular')]]);
        config()->set('php-pdf.fonts.provider', LiberationFontProvider::class);
        $this->refreshFactory();

        $bytes = Pdf::fromHtml('<p style="font-family: Arial">x</p>')->bytes();

        self::assertMatchesRegularExpression('#/BaseFont /(?:[A-Z]{6}\+)?LiberationSerif(?![\w-])#', $bytes);
        self::assertDoesNotMatchRegularExpression('#/BaseFont /(?:[A-Z]{6}\+)?LiberationSans#', $bytes);
    }

    #[Test]
    public function a_provider_that_is_not_a_font_provider_is_refused(): void
    {
        config()->set('php-pdf.fonts.provider', \stdClass::class);
        $this->refreshFactory();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('not a Dskripchenko\PhpPdf\Font\FontProvider');
        Pdf::fromHtml('<p>x</p>')->bytes();
    }
}
