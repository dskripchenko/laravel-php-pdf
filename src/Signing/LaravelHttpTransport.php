<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf\Signing;

use Dskripchenko\PhpPdf\Pdf\Signature\Transport\HttpGetTransport;
use Dskripchenko\PhpPdf\Pdf\Signature\Transport\HttpResponse;
use Dskripchenko\PhpPdf\Pdf\Signature\Transport\HttpTransport;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

/**
 * php-pdf's HTTP needs — timestamp authorities, OCSP responders, CRLs,
 * issuer certificates — served by Laravel's HTTP client: its proxy and
 * TLS settings apply, and `Http::fake()` works in tests.
 */
final class LaravelHttpTransport implements HttpTransport, HttpGetTransport
{
    public function __construct(private readonly Factory $http) {}

    public function post(string $url, string $body, array $headers, float $timeoutSeconds): HttpResponse
    {
        $contentType = $headers['Content-Type'] ?? 'application/octet-stream';
        unset($headers['Content-Type']);

        return $this->send(fn () => $this->http->withHeaders($headers)->timeout((int) ceil($timeoutSeconds))
            ->withBody($body, $contentType)->post($url), $url);
    }

    public function get(string $url, array $headers, float $timeoutSeconds): HttpResponse
    {
        return $this->send(fn () => $this->http->withHeaders($headers)->timeout((int) ceil($timeoutSeconds))->get($url), $url);
    }

    /**
     * @param  \Closure(): Response  $request
     */
    private function send(\Closure $request, string $url): HttpResponse
    {
        try {
            $response = $request();
        } catch (ConnectionException $e) {
            throw new \RuntimeException("HTTP request to $url failed: ".$e->getMessage(), previous: $e);
        }
        $headers = [];
        foreach ($response->headers() as $name => $values) {
            $headers[strtolower((string) $name)] = implode(', ', (array) $values);
        }

        return new HttpResponse($response->status(), $response->body(), $headers);
    }
}
