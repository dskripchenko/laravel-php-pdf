<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf\Signing;

use Dskripchenko\PhpPdf\Pdf\Signature\Transport\HttpGetTransport;
use Dskripchenko\PhpPdf\Pdf\Signature\Transport\HttpResponse;
use Dskripchenko\PhpPdf\Pdf\Signature\Transport\HttpTransport;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * php-pdf's HTTP needs — timestamp authorities, OCSP responders, CRLs,
 * issuer certificates — served by Laravel's HTTP client: its proxy and
 * TLS settings apply, and `Http::fake()` works in tests.
 *
 * Every request — and every redirect — passes the {@see UrlPolicy} first;
 * responses larger than its limit are cut off.
 */
final class LaravelHttpTransport implements HttpTransport, HttpGetTransport
{
    private readonly UrlPolicy $policy;

    public function __construct(private readonly Factory $http, ?UrlPolicy $policy = null)
    {
        $this->policy = $policy ?? new UrlPolicy;
    }

    public function post(string $url, string $body, array $headers, float $timeoutSeconds): HttpResponse
    {
        $contentType = $headers['Content-Type'] ?? 'application/octet-stream';
        unset($headers['Content-Type']);

        return $this->send($url, $headers, $timeoutSeconds, static fn (PendingRequest $r): Response => $r->withBody($body, $contentType)->post($url));
    }

    public function get(string $url, array $headers, float $timeoutSeconds): HttpResponse
    {
        return $this->send($url, $headers, $timeoutSeconds, static fn (PendingRequest $r): Response => $r->get($url));
    }

    /**
     * @param  array<string, string>  $headers
     * @param  \Closure(PendingRequest): Response  $request
     */
    private function send(string $url, array $headers, float $timeoutSeconds, \Closure $request): HttpResponse
    {
        $pinned = $this->policy->check($url);
        $limit = $this->policy->maxResponseBytes;

        $pending = $this->http->withHeaders($headers)
            ->timeout((int) ceil($timeoutSeconds))
            ->withOptions([
                'allow_redirects' => $this->policy->maxRedirects > 0 ? [
                    'max' => $this->policy->maxRedirects,
                    'protocols' => ['http', 'https'],
                    // A redirect is a new URL: it passes the policy too.
                    'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $uri): void {
                        $this->policy->check((string) $uri);
                    },
                ] : false,
                'progress' => static function (int $total, int $downloaded) use ($limit, $url): void {
                    if ($total > $limit || $downloaded > $limit) {
                        throw new UrlNotAllowed("The response from $url is larger than $limit bytes");
                    }
                },
                // Connect to the address that was checked, not to whatever
                // the name resolves to a moment later.
                ...($pinned !== null && defined('CURLOPT_RESOLVE') && filter_var(trim((string) parse_url($url, PHP_URL_HOST), '[]'), FILTER_VALIDATE_IP) === false ? ['curl' => [CURLOPT_RESOLVE => [self::resolveEntry($url, $pinned)]]] : []),
            ]);

        try {
            $response = $request($pending);
        } catch (ConnectionException $e) {
            throw new \RuntimeException("HTTP request to $url failed: ".$e->getMessage(), previous: $e);
        } catch (\Throwable $e) {
            // Guzzle wraps what the policy callbacks throw.
            for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
                if ($cause instanceof UrlNotAllowed) {
                    throw $cause;
                }
            }
            throw $e;
        }
        $body = $response->body();
        if (strlen($body) > $limit) {
            throw new UrlNotAllowed("The response from $url is larger than $limit bytes");
        }
        $headers = [];
        foreach ($response->headers() as $name => $values) {
            $headers[strtolower((string) $name)] = implode(', ', (array) $values);
        }

        return new HttpResponse($response->status(), $body, $headers);
    }

    /** `host:port:address` for CURLOPT_RESOLVE. */
    private static function resolveEntry(string $url, string $ip): string
    {
        $parts = parse_url($url) ?: [];
        $scheme = strtolower((string) ($parts['scheme'] ?? 'http'));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $host = trim((string) ($parts['host'] ?? ''), '[]');

        return sprintf('%s:%d:%s', $host, $port, str_contains($ip, ':') ? "[$ip]" : $ip);
    }
}
