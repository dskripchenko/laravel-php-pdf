<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf;

use Dskripchenko\PhpPdf\Document;
use Dskripchenko\PhpPdf\Layout\Engine;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A document paired with the configured engine, ready to be rendered as
 * bytes, a file, or an HTTP response.
 */
class PendingPdf
{
    public function __construct(
        private readonly Document $document,
        private readonly ?Engine $engine = null,
    ) {}

    public function bytes(): string
    {
        return $this->document->toBytes($this->engine);
    }

    /**
     * @return int  Bytes written.
     */
    public function save(string $path): int
    {
        return $this->document->toFile($path, $this->engine);
    }

    /**
     * Inline HTTP response (opens in the browser's viewer).
     */
    public function inline(string $filename = 'document.pdf'): Response
    {
        return $this->response($filename, inline: true);
    }

    /**
     * Attachment HTTP response (browser downloads the file).
     */
    public function download(string $filename = 'document.pdf'): Response
    {
        return $this->response($filename, inline: false);
    }

    public function response(string $filename = 'document.pdf', bool $inline = true): Response
    {
        $bytes = $this->bytes();

        return new Response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => sprintf(
                '%s; filename="%s"',
                $inline ? 'inline' : 'attachment',
                addslashes($filename),
            ),
        ]);
    }

    /**
     * Streamed HTTP response: the document is rendered straight into the
     * output buffer via php-pdf's streaming emitter, never held in memory
     * as one string. Prefer this over {@see response()} for very large
     * documents (thousands of pages, many images). No Content-Length —
     * the size is unknown until rendering finishes.
     */
    public function stream(string $filename = 'document.pdf', bool $inline = true): StreamedResponse
    {
        $document = $this->document;
        $engine = $this->engine;

        return new StreamedResponse(
            static function () use ($document, $engine): void {
                $out = fopen('php://output', 'wb');
                if ($out === false) {
                    throw new \RuntimeException('Cannot open php://output for PDF streaming.');
                }
                try {
                    $document->toStream($out, $engine);
                } finally {
                    fclose($out);
                }
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf(
                    '%s; filename="%s"',
                    $inline ? 'inline' : 'attachment',
                    addslashes($filename),
                ),
            ],
        );
    }

    /**
     * Streamed attachment response (browser downloads the file).
     */
    public function streamDownload(string $filename = 'document.pdf'): StreamedResponse
    {
        return $this->stream($filename, inline: false);
    }

    /**
     * The underlying document, for native-API features.
     */
    public function document(): Document
    {
        return $this->document;
    }
}
