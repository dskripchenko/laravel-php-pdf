<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf;

use Dskripchenko\PhpPdf\Document;
use Dskripchenko\PhpPdf\Layout\Engine;
use Dskripchenko\PhpPdf\Pdf\Signature\PdfSigner;
use Dskripchenko\PhpPdf\Pdf\SignatureConfig;
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
        private readonly ?PdfFactory $factory = null,
        private readonly bool $signedAfterwards = false,
    ) {}

    /**
     * The same document, signed with the configured key (see
     * {@see PdfFactory::signatureConfig()} for the options). A
     * SignatureField in the document is signed with its stamp filled from
     * the certificate; a document without one gets an invisible signature.
     *
     * @param  array<string, mixed>|SignatureConfig  $options
     */
    public function signed(array|SignatureConfig $options = []): self
    {
        $factory = $this->factory ?? throw new \LogicException('A PendingPdf made outside the Pdf facade cannot sign');
        $config = $options instanceof SignatureConfig ? $options : $factory->signatureConfig($options);
        $document = new Document(...[...get_object_vars($this->document), 'signature' => $config]);

        return new self($document, $this->engine, $factory, signedAfterwards: ! self::hasSignatureField($document));
    }

    public function bytes(): string
    {
        if ($this->signedAfterwards) {
            $signature = $this->document->signature;
            $unsigned = new Document(...[...get_object_vars($this->document), 'signature' => null]);

            return PdfSigner::fromBytes($unsigned->toBytes($this->engine))->sign($signature ?? throw new \LogicException('No signature'));
        }

        return $this->document->toBytes($this->engine);
    }

    /**
     * @return int  Bytes written.
     */
    public function save(string $path): int
    {
        if ($this->signedAfterwards) {
            $bytes = $this->bytes();
            if (@file_put_contents($path, $bytes) === false) {
                throw new \RuntimeException("Cannot write PDF file: $path");
            }

            return strlen($bytes);
        }

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
        // A signature added afterwards needs the whole document first.
        $signedBytes = $this->signedAfterwards ? $this->bytes() : null;

        return new StreamedResponse(
            static function () use ($document, $engine, $signedBytes): void {
                if ($signedBytes !== null) {
                    echo $signedBytes;

                    return;
                }
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

    /** Whether any section holds a signature field, at the top level or in a table. */
    private static function hasSignatureField(Document $document): bool
    {
        $walk = static function (mixed $node) use (&$walk): bool {
            if ($node instanceof \Dskripchenko\PhpPdf\Element\SignatureField) {
                return true;
            }
            if ($node instanceof \Dskripchenko\PhpPdf\Element\FormField) {
                return $node->type === \Dskripchenko\PhpPdf\Element\FormField::TYPE_SIGNATURE;
            }
            if (is_array($node)) {
                foreach ($node as $child) {
                    if ($walk($child)) {
                        return true;
                    }
                }

                return false;
            }
            // Only the document tree: elements and sections, not fonts or images.
            if (is_object($node) && (str_starts_with($node::class, 'Dskripchenko\\PhpPdf\\Element\\') || $node instanceof \Dskripchenko\PhpPdf\Section)) {
                foreach (get_object_vars($node) as $value) {
                    if (is_array($value) || is_object($value)) {
                        if ($walk($value)) {
                            return true;
                        }
                    }
                }
            }

            return false;
        };

        return $walk($document->sections());
    }
}
