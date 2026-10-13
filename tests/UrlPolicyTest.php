<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf\Tests;

use Dskripchenko\LaravelPhpPdf\Facades\Pdf;
use Dskripchenko\LaravelPhpPdf\Signing\LaravelHttpTransport;
use Dskripchenko\LaravelPhpPdf\Signing\UrlNotAllowed;
use Dskripchenko\LaravelPhpPdf\Signing\UrlPolicy;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class UrlPolicyTest extends TestCase
{
    /** @param list<string> $ips */
    private static function policy(array $ips, array $allowed = []): UrlPolicy
    {
        return new UrlPolicy(blockPrivateNetworks: true, allowedCidrs: $allowed, resolver: static fn (string $host): array => $ips);
    }

    /** @return iterable<string, array{string}> */
    public static function nonPublic(): iterable
    {
        foreach (['127.0.0.1', '10.1.2.3', '172.20.0.1', '192.168.1.1', '169.254.169.254', '100.64.0.1', '0.0.0.0',
            '224.0.0.1', '198.18.0.1', '::1', 'fe80::1', 'fd00::1', '::ffff:127.0.0.1'] as $ip) {
            yield $ip => [$ip];
        }
    }

    #[Test]
    #[DataProvider('nonPublic')]
    public function non_public_addresses_are_refused(string $ip): void
    {
        $this->expectException(UrlNotAllowed::class);
        self::policy([$ip])->check('http://ocsp.example.test/');
    }

    #[Test]
    public function one_non_public_address_among_public_ones_is_enough_to_refuse(): void
    {
        $this->expectException(UrlNotAllowed::class);
        self::policy(['93.184.215.14', '10.0.0.5'])->check('http://crl.example.test/ca.crl');
    }

    #[Test]
    public function a_public_address_is_allowed_and_pinned(): void
    {
        self::assertSame('93.184.215.14', self::policy(['93.184.215.14'])->check('https://tsa.example.test/tsr'));
    }

    #[Test]
    public function literal_addresses_are_checked_without_dns(): void
    {
        self::assertSame('93.184.215.14', self::policy([])->check('http://93.184.215.14/'));
        $this->expectException(UrlNotAllowed::class);
        self::policy([])->check('http://[::1]:8080/');
    }

    #[Test]
    public function allowed_cidrs_let_an_internal_service_through(): void
    {
        self::assertSame('10.20.0.7', self::policy(['10.20.0.7'], ['10.20.0.0/16'])->check('http://tsa.corp/'));
    }

    #[Test]
    public function other_schemes_and_credentials_are_refused_even_when_private_networks_are_allowed(): void
    {
        $open = new UrlPolicy;
        self::assertNull($open->check('http://10.0.0.1/'));
        foreach (['file:///etc/passwd', 'gopher://x/', 'ftp://x/', 'http://user:secret@tsa.example/'] as $url) {
            try {
                $open->check($url);
                self::fail("$url was allowed");
            } catch (UrlNotAllowed) {
                self::addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function an_unresolvable_host_is_refused(): void
    {
        $this->expectException(UrlNotAllowed::class);
        self::policy([])->check('http://nowhere.invalid/');
    }

    #[Test]
    public function the_transport_checks_before_sending(): void
    {
        Http::fake();
        $transport = new LaravelHttpTransport(app(Factory::class), self::policy(['169.254.169.254']));

        try {
            $transport->get('http://metadata.example.test/latest/meta-data/', [], 5);
            self::fail('The request was sent');
        } catch (UrlNotAllowed $e) {
            self::assertStringContainsString('169.254.169.254', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    #[Test]
    public function responses_over_the_limit_are_refused(): void
    {
        Http::fake(['crl.example.test/*' => Http::response(str_repeat('x', 2048))]);
        $transport = new LaravelHttpTransport(app(Factory::class), new UrlPolicy(maxResponseBytes: 1024));

        $this->expectException(UrlNotAllowed::class);
        $transport->get('http://crl.example.test/big.crl', [], 5);
    }

    #[Test]
    public function the_policy_comes_from_config(): void
    {
        config(['php-pdf.signing.network' => ['block_private_networks' => true, 'allowed_cidrs' => ['10.0.0.0/8'], 'max_response_bytes' => 4096]]);

        $policy = Pdf::urlPolicy();
        self::assertTrue($policy->blockPrivateNetworks);
        self::assertSame(['10.0.0.0/8'], $policy->allowedCidrs);
        self::assertSame(4096, $policy->maxResponseBytes);
    }
}
