<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf\Tests;

use Dskripchenko\LaravelPhpPdf\Facades\Pdf;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StreamingTest extends TestCase
{
    private function capture(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    #[Test]
    public function stream_sends_the_pdf_without_buffering_headers(): void
    {
        $response = Pdf::fromHtml('<h1>Streamed</h1>')->stream('report.pdf');

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame('inline; filename="report.pdf"', $response->headers->get('Content-Disposition'));
        self::assertFalse($response->headers->has('Content-Length'));
        self::assertStringStartsWith('%PDF-', $this->capture($response));
    }

    #[Test]
    public function stream_download_uses_attachment_disposition(): void
    {
        $response = Pdf::fromHtml('<p>x</p>')->streamDownload('a.pdf');

        self::assertSame('attachment; filename="a.pdf"', $response->headers->get('Content-Disposition'));
    }

    #[Test]
    public function streamed_bytes_match_the_buffered_path(): void
    {
        $html = '<h1>Parity</h1><p>Streamed and buffered output must agree.</p>';

        $streamed = $this->capture(Pdf::fromHtml($html)->stream());
        $buffered = Pdf::fromHtml($html)->bytes();

        // CreationDate differs between the two renders — normalize it.
        $strip = fn (string $b): string => (string) preg_replace('@D:\d{14}@', 'D:0', $b);
        self::assertSame(strlen($buffered), strlen($streamed));
        self::assertSame($strip($buffered), $strip($streamed));
    }
}
