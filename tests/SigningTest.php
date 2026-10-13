<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf\Tests;

use Dskripchenko\LaravelPhpPdf\Facades\Pdf;
use Dskripchenko\LaravelPhpPdf\PdfFactory;
use Dskripchenko\PhpPdf\Document;
use Dskripchenko\PhpPdf\Element\Paragraph;
use Dskripchenko\PhpPdf\Element\Run;
use Dskripchenko\PhpPdf\Element\SignatureField;
use Dskripchenko\PhpPdf\Pdf\Reader\PdfDictionary;
use Dskripchenko\PhpPdf\Pdf\Reader\ReaderDocument;
use Dskripchenko\PhpPdf\Pdf\Signature\DocMdpPermission;
use Dskripchenko\PhpPdf\Pdf\Signature\FieldLock;
use Dskripchenko\PhpPdf\Pdf\Signature\SubFilter;
use Dskripchenko\PhpPdf\Pdf\Signature\Tsa\TsaException;
use Dskripchenko\PhpPdf\Section;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

final class SigningTest extends TestCase
{
    private static string $cert = '';

    private static string $key = '';

    public static function setUpBeforeClass(): void
    {
        if (! function_exists('openssl_pkey_new')) {
            return;
        }
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'Laravel Test Signer'], $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 30, ['digest_alg' => 'sha256']);
        openssl_x509_export($cert, self::$cert);
        openssl_pkey_export($key, self::$key);
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (self::$cert === '') {
            self::markTestSkipped('openssl extension not available');
        }
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('php-pdf.signing.certificate', self::$cert);
        $app['config']->set('php-pdf.signing.key', self::$key);
        $app['config']->set('php-pdf.signing.reason', 'Approved');
        $app['config']->set('php-pdf.validation.trust', [self::$cert]);
    }

    #[Test]
    public function a_generated_document_without_a_field_gets_an_invisible_signature(): void
    {
        $pdf = Pdf::fromHtml('<p>Invoice</p>')->signed()->bytes();

        self::assertStringContainsString('/SubFilter /ETSI.CAdES.detached', $pdf);
        $report = Pdf::validate($pdf);
        self::assertTrue($report->allValid(), implode('; ', $report->signatures[0]->errors ?? []));
        self::assertSame('Laravel Test Signer', $report->signatures[0]->signer?->subjectCommonName());
    }

    #[Test]
    public function a_signature_field_in_the_document_is_signed_with_its_stamp(): void
    {
        $document = new Document(new Section([
            new Paragraph([new Run('Contract')]),
            new SignatureField('approval', appearance: \Dskripchenko\PhpPdf\Pdf\Signature\Appearance\SignatureAppearance::simple()),
        ]));
        $pdf = Pdf::render($document)->signed(['reason' => 'Signed by HR'])->bytes();

        $report = Pdf::validate($pdf);
        self::assertTrue($report->allValid());
        self::assertNotNull($report->signature('approval'), 'The document field itself is signed');
        self::assertStringContainsString('/Reason (Signed by HR)', $pdf);
        self::assertCount(1, $report->signatures, 'No extra invisible field');
    }

    #[Test]
    public function signed_documents_save_and_stream(): void
    {
        $pending = Pdf::fromHtml('<p>x</p>')->signed();
        $path = sys_get_temp_dir().'/laravel-php-pdf-'.bin2hex(random_bytes(4)).'.pdf';
        try {
            $pending->save($path);
            self::assertTrue(Pdf::validate((string) file_get_contents($path))->allValid());
        } finally {
            @unlink($path);
        }

        ob_start();
        $pending->stream()->sendContent();
        self::assertTrue(Pdf::validate((string) ob_get_clean())->allValid());
    }

    #[Test]
    public function an_existing_pdf_is_signed_and_signed_again(): void
    {
        $original = Pdf::fromHtml('<p>Original</p>')->bytes();
        $once = Pdf::sign($original);
        $twice = Pdf::sign($once, ['reason' => 'Second']);

        self::assertStringStartsWith($original, $once);
        self::assertStringStartsWith($once, $twice);
        $report = Pdf::validate($twice);
        self::assertCount(2, $report->signatures);
        self::assertTrue($report->allValid());
    }

    #[Test]
    public function options_map_onto_the_signature_config(): void
    {
        $config = Pdf::signatureConfig(['certification' => 2, 'lock' => 'all', 'format' => 'pkcs7', 'field' => 'sig', 'timestamp' => false]);

        self::assertSame(DocMdpPermission::FormFilling, $config->certification);
        self::assertEquals(FieldLock::all(), $config->lockFields);
        self::assertSame(SubFilter::Pkcs7Detached, $config->subFilter);
        self::assertSame('sig', $config->fieldName);
        self::assertSame('Approved', $config->reason);
        self::assertNull($config->timestampAuthority);
        self::assertEquals(FieldLock::only(['a', 'b']), Pdf::signatureConfig(['lock' => ['a', 'b']])->lockFields);
    }

    #[Test]
    public function a_pkcs12_container_can_be_configured(): void
    {
        $p12 = '';
        openssl_pkcs12_export(self::$cert, $p12, self::$key, 'secret');
        $path = sys_get_temp_dir().'/laravel-php-pdf-'.bin2hex(random_bytes(4)).'.p12';
        file_put_contents($path, $p12);
        try {
            $factory = new PdfFactory(['signing' => ['pkcs12' => $path, 'pkcs12_password' => 'secret'], 'validation' => ['trust' => [self::$cert]]]);
            self::assertTrue($factory->validate($factory->sign($factory->fromHtml('<p>x</p>')->bytes()))->allValid());
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function without_a_key_signing_explains_what_to_configure(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/php-pdf\.signing/');
        (new PdfFactory)->signatureConfig();
    }

    #[Test]
    public function the_timestamp_authority_goes_through_laravels_http_client(): void
    {
        config()->set('php-pdf.signing.timestamp.url', 'https://tsa.example/tsr');
        config()->set('php-pdf.signing.timestamp.username', 'user');
        $this->app->forgetInstance(PdfFactory::class);
        Http::fake(['tsa.example/*' => Http::response('busy', 503)]);

        try {
            Pdf::sign(Pdf::fromHtml('<p>x</p>')->bytes());
            self::fail('The TSA failure was swallowed');
        } catch (TsaException $e) {
            self::assertStringContainsString('HTTP 503', $e->getMessage());
        }
        Http::assertSent(static fn (Request $request): bool => $request->url() === 'https://tsa.example/tsr'
            && $request->hasHeader('Content-Type', 'application/timestamp-query')
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('user:')));
    }

    #[Test]
    public function ltv_embeds_the_validation_data(): void
    {
        Http::fake();
        $ltv = Pdf::ltv(Pdf::sign(Pdf::fromHtml('<p>x</p>')->bytes()), archive: false);

        $doc = ReaderDocument::fromBytes($ltv);
        self::assertInstanceOf(PdfDictionary::class, $doc->deref($doc->catalog()->get('DSS')));
        self::assertTrue(Pdf::validate($ltv)->allValid());
    }

    #[Test]
    public function a_signed_form_is_filled_without_breaking_the_signature(): void
    {
        $pdf = \Dskripchenko\PhpPdf\Pdf\Document::new();
        $page = $pdf->addPage();
        $page->addFormField('text', 'name', 72, 700, 200, 20);
        $page->addSignatureField('sig', 72, 600, 200, 50);
        $signed = Pdf::sign($pdf->toBytes());

        $filled = Pdf::fillForm($signed)->setValue('name', 'Jane')->toBytes(incremental: true);
        $report = Pdf::validate($filled);
        self::assertTrue($report->allValid(), implode('; ', $report->signatures[0]->errors));
    }

    #[Test]
    public function the_transport_maps_laravel_responses(): void
    {
        Http::fake(['crl.example/*' => Http::response('crl-bytes', 200, ['Content-Type' => 'application/pkix-crl'])]);
        $response = Pdf::httpTransport()->get('https://crl.example/ca.crl', ['Accept' => '*/*'], 5);

        self::assertSame(200, $response->status);
        self::assertSame('crl-bytes', $response->body);
        self::assertSame('application/pkix-crl', $response->header('Content-Type'));
    }

    #[Test]
    public function a_detached_signature_is_made_and_verified(): void
    {
        $content = "any bytes\n";
        $p7s = Pdf::signDetached($content, ['timestamp' => false]);

        self::assertTrue(Pdf::verifyDetached($content, $p7s)->isValid());
        self::assertFalse(Pdf::verifyDetached($content.'x', $p7s)->isValid());

        $dir = sys_get_temp_dir().'/lpp-detached-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents("$dir/doc.bin", $content);
        file_put_contents("$dir/doc.bin.p7s", $p7s);
        try {
            self::assertTrue(Pdf::verifyDetached("$dir/doc.bin", "$dir/doc.bin.p7s")->isValid());
        } finally {
            array_map('unlink', (array) glob("$dir/*"));
            rmdir($dir);
        }
    }
}
