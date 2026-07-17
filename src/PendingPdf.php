<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf;

use Dskripchenko\PhpPdf\Document;
use Dskripchenko\PhpPdf\Layout\Engine;
use Illuminate\Http\Response;

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
     * The underlying document, for native-API features.
     */
    public function document(): Document
    {
        return $this->document;
    }
}
