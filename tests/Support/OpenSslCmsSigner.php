<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf\Tests\Support;

use Dskripchenko\PhpPdf\Pdf\Signature\Signer\Certificate;
use Dskripchenko\PhpPdf\Pdf\Signature\Signer\CmsSigner;
use Dskripchenko\PhpPdf\Pdf\Signature\Signer\DigestAlgorithm;

/** A CmsSigner that makes the CMS with openssl_cms_sign(), as CryptoPro would with its own CSP. */
final class OpenSslCmsSigner implements CmsSigner
{
    public int $calls = 0;

    public function __construct(private readonly string $certificatePem, private readonly string $keyPem) {}

    public function certificate(): Certificate
    {
        return Certificate::fromPem($this->certificatePem);
    }

    public function chain(): array
    {
        return [];
    }

    public function digestAlgorithm(): DigestAlgorithm
    {
        return DigestAlgorithm::Sha256;
    }

    public function signCms(string $content, string $digest): string
    {
        $this->calls++;
        $in = (string) tempnam(sys_get_temp_dir(), 'cms');
        $out = (string) tempnam(sys_get_temp_dir(), 'cms');
        try {
            file_put_contents($in, $content);
            if (! openssl_cms_sign($in, $out, $this->certificatePem, $this->keyPem, null, OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY, OPENSSL_ENCODING_DER)) {
                throw new \RuntimeException('openssl_cms_sign failed');
            }

            return (string) file_get_contents($out);
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }
}
