<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf\Signing;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Which addresses signing may reach.
 *
 * Signing talks to URLs it does not choose: a timestamp authority set by a
 * user, OCSP responders, CRLs and issuer certificates named inside
 * certificates someone uploaded. A certificate whose AIA points at
 * `http://169.254.169.254/` turns signing into a request for the cloud's
 * metadata. With `blockPrivateNetworks` every address the host resolves to
 * must be public — loopback, private, link-local, shared (CGN) and reserved
 * ranges are refused unless `allowedCidrs` lets them through — and the
 * request goes to the address that was checked, so the name cannot be
 * re-resolved elsewhere in between.
 */
final class UrlPolicy
{
    /** Beyond IpUtils::PRIVATE_SUBNETS (loopback, RFC 1918, link-local, CGN…), still not the public internet. */
    private const RESERVED = [
        '192.0.0.0/24',    // IETF protocol assignments
        '192.0.2.0/24', '198.51.100.0/24', '203.0.113.0/24', // documentation
        '198.18.0.0/15',   // benchmarking
        '224.0.0.0/4',     // multicast
        '64:ff9b::/96',    // NAT64
        '2001:db8::/32',   // documentation
        'ff00::/8',        // multicast
    ];

    /**
     * @param  list<string>  $allowedCidrs  Addresses allowed even when private (an internal TSA).
     * @param  ?\Closure(string): list<string>  $resolver  Host → IPs; DNS by default.
     */
    public function __construct(
        public readonly bool $blockPrivateNetworks = false,
        public readonly array $allowedCidrs = [],
        public readonly int $maxResponseBytes = 20 * 1024 * 1024,
        public readonly int $maxRedirects = 3,
        private readonly ?\Closure $resolver = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config  `php-pdf.signing.network`.
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            blockPrivateNetworks: (bool) ($config['block_private_networks'] ?? false),
            allowedCidrs: array_values(array_map('strval', (array) ($config['allowed_cidrs'] ?? []))),
            maxResponseBytes: (int) ($config['max_response_bytes'] ?? 20 * 1024 * 1024),
            maxRedirects: (int) ($config['max_redirects'] ?? 3),
        );
    }

    /**
     * Checks a URL and returns the address to connect to (null when no
     * address is pinned — private networks allowed).
     *
     * @throws UrlNotAllowed
     */
    public function check(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new UrlNotAllowed("Only http and https URLs are allowed: $url");
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UrlNotAllowed("URLs with credentials are not allowed: $scheme://$host");
        }
        if (! $this->blockPrivateNetworks) {
            return null;
        }

        $bare = trim($host, '[]');
        $ips = filter_var($bare, FILTER_VALIDATE_IP) !== false ? [$bare] : $this->resolve($bare);
        if ($ips === []) {
            throw new UrlNotAllowed("Cannot resolve $host");
        }
        foreach ($ips as $ip) {
            if (! $this->isAllowedIp($ip)) {
                throw new UrlNotAllowed("$host resolves to $ip, a non-public address");
            }
        }

        return $ips[0];
    }

    public function isAllowedIp(string $ip): bool
    {
        $ip = self::unmapIpv4($ip);
        if ($this->allowedCidrs !== [] && IpUtils::checkIp($ip, $this->allowedCidrs)) {
            return true;
        }

        return ! IpUtils::checkIp($ip, [...IpUtils::PRIVATE_SUBNETS, ...self::RESERVED]);
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        if ($this->resolver !== null) {
            return ($this->resolver)($host);
        }
        $ips = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = (string) $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    /** `::ffff:127.0.0.1` is 127.0.0.1. */
    private static function unmapIpv4(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed !== false && strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return $ip;
    }
}
