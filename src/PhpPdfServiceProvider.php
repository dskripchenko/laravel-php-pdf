<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf;

use Dskripchenko\PhpPdf\Document;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Support\ServiceProvider;

class PhpPdfServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/php-pdf.php', 'php-pdf');

        $this->app->singleton(PdfFactory::class, function ($app): PdfFactory {
            return new PdfFactory((array) $app['config']->get('php-pdf', []));
        });
        $this->app->alias(PdfFactory::class, 'php-pdf');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/php-pdf.php' => $this->app->configPath('php-pdf.php'),
            ], 'php-pdf-config');
        }

        $this->registerResponseMacro();
    }

    /**
     * response()->pdf($document, 'invoice.pdf')
     *
     * Accepts a PendingPdf, a php-pdf Document (AST or low-level
     * Pdf\Document), or raw PDF bytes.
     */
    private function registerResponseMacro(): void
    {
        if (ResponseFacade::hasMacro('pdf')) {
            return;
        }

        ResponseFacade::macro('pdf', function (
            mixed $pdf,
            string $filename = 'document.pdf',
            bool $inline = true,
        ): Response {
            /** @var ResponseFactory $this */
            $app = app();
            $bytes = match (true) {
                $pdf instanceof PendingPdf => $pdf->bytes(),
                $pdf instanceof Document => $app->make(PdfFactory::class)->render($pdf)->bytes(),
                $pdf instanceof \Dskripchenko\PhpPdf\Pdf\Document => $pdf->toBytes(),
                is_string($pdf) => $pdf,
                default => throw new \InvalidArgumentException(
                    'response()->pdf() expects PendingPdf, Document, Pdf\Document, or PDF bytes.',
                ),
            };

            return new Response($bytes, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Length' => (string) strlen($bytes),
                'Content-Disposition' => sprintf(
                    '%s; filename="%s"',
                    $inline ? 'inline' : 'attachment',
                    addslashes($filename),
                ),
            ]);
        });
    }
}
