<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf\Tests;

use Dskripchenko\LaravelPhpPdf\Facades\Pdf;
use Dskripchenko\PhpPdf\Document;
use Dskripchenko\PhpPdf\Element\Paragraph;
use Dskripchenko\PhpPdf\Element\Run;
use Dskripchenko\PhpPdf\Pdf\Document as LowLevelDocument;
use Dskripchenko\PhpPdf\Section;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;

final class ResponseMacroTest extends TestCase
{
    #[Test]
    public function macro_accepts_pending_pdf(): void
    {
        /** @var Response $response */
        $response = response()->pdf(Pdf::fromHtml('<p>macro</p>'), 'report.pdf');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame('inline; filename="report.pdf"', $response->headers->get('Content-Disposition'));
        self::assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    #[Test]
    public function macro_accepts_ast_document_and_download_disposition(): void
    {
        $document = new Document(new Section([new Paragraph([new Run('ast')])]));

        /** @var Response $response */
        $response = response()->pdf($document, 'ast.pdf', inline: false);

        self::assertSame('attachment; filename="ast.pdf"', $response->headers->get('Content-Disposition'));
        self::assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    #[Test]
    public function macro_accepts_low_level_document_and_raw_bytes(): void
    {
        $low = LowLevelDocument::new();
        $low->addPage();

        /** @var Response $first */
        $first = response()->pdf($low);
        self::assertStringStartsWith('%PDF-', (string) $first->getContent());

        /** @var Response $second */
        $second = response()->pdf($first->getContent());
        self::assertSame('application/pdf', $second->headers->get('Content-Type'));
    }

    #[Test]
    public function macro_rejects_unsupported_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        response()->pdf(42);
    }

    #[Test]
    public function pending_pdf_download_and_inline_helpers(): void
    {
        $pdf = Pdf::fromHtml('<p>helpers</p>');

        self::assertSame(
            'attachment; filename="a.pdf"',
            $pdf->download('a.pdf')->headers->get('Content-Disposition'),
        );
        self::assertSame(
            'inline; filename="b.pdf"',
            $pdf->inline('b.pdf')->headers->get('Content-Disposition'),
        );
    }
}
