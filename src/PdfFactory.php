<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf;

use Dskripchenko\PhpPdf\Build\DocumentBuilder;
use Dskripchenko\PhpPdf\Document;
use Dskripchenko\PhpPdf\Layout\Engine;
use Dskripchenko\PhpPdf\Pdf\Forms\ExistingFormFiller;
use Dskripchenko\PhpPdf\Pdf\PdfFont;
use Dskripchenko\PhpPdf\Font\ChainedFontProvider;
use Dskripchenko\PhpPdf\Font\FontProvider;
use Dskripchenko\PhpPdf\Font\Ttf\TtfFile;
use Dskripchenko\LaravelPhpPdf\Signing\LaravelHttpTransport;
use Dskripchenko\LaravelPhpPdf\Signing\UrlPolicy;
use Dskripchenko\PhpPdf\Pdf\Signature\Appearance\SignatureAppearance;
use Dskripchenko\PhpPdf\Pdf\Signature\Cms\DetachedSignature;
use Dskripchenko\PhpPdf\Pdf\Signature\DocMdpPermission;
use Dskripchenko\PhpPdf\Pdf\Signature\FieldLock;
use Dskripchenko\PhpPdf\Pdf\Signature\Ltv\CertificateFetcher;
use Dskripchenko\PhpPdf\Pdf\Signature\Ltv\CrlFetcher;
use Dskripchenko\PhpPdf\Pdf\Signature\Ltv\OcspClient;
use Dskripchenko\PhpPdf\Pdf\Signature\Ltv\OnlineRevocationSource;
use Dskripchenko\PhpPdf\Pdf\Signature\PdfSigner;
use Dskripchenko\PhpPdf\Pdf\Signature\Signer\CmsSigner;
use Dskripchenko\PhpPdf\Pdf\Signature\Signer\OpenSslSigner;
use Dskripchenko\PhpPdf\Pdf\Signature\Signer\Signer;
use Dskripchenko\PhpPdf\Pdf\Signature\SubFilter;
use Dskripchenko\PhpPdf\Pdf\Signature\Tsa\TsaClient;
use Dskripchenko\PhpPdf\Pdf\Signature\Validation\SignatureReport;
use Dskripchenko\PhpPdf\Pdf\Signature\Validation\SignatureValidator;
use Dskripchenko\PhpPdf\Pdf\Signature\Validation\TrustStore;
use Dskripchenko\PhpPdf\Pdf\Signature\Validation\ValidationReport;
use Dskripchenko\PhpPdf\Pdf\SignatureConfig;
use Dskripchenko\PhpPdf\Section;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use Dskripchenko\PhpPdf\Style\Orientation;
use Dskripchenko\PhpPdf\Style\PageMargins;
use Dskripchenko\PhpPdf\Style\PageSetup;
use Dskripchenko\PhpPdf\Style\PaperSize;

/**
 * Entry point behind the Pdf facade. Turns the php-pdf config file into a
 * ready Engine (fonts) and Section template (paper, margins), so
 * application code deals with content only.
 */
class PdfFactory
{
    private ?Engine $engine = null;

    private bool $engineBuilt = false;

    /**
     * @param  array<string, mixed>  $config  The `php-pdf` config array.
     */
    /** The signer from `php-pdf.signing.signer`, resolved once. */
    private Signer|CmsSigner|null $boundSigner = null;

    public function __construct(
        private readonly array $config = [],
        private readonly ?HttpFactory $http = null,
        private readonly ?Container $container = null,
    ) {}

    /**
     * Parse HTML into a renderable document.
     *
     * @param  array<string, string>  $metadata
     */
    public function fromHtml(string $html, array $metadata = []): PendingPdf
    {
        $document = Document::fromHtml(
            $html,
            metadata: $metadata + $this->defaultMetadata(),
            sectionTemplate: new Section(pageSetup: $this->pageSetup()),
        );

        return new PendingPdf($document, $this->engine(), $this);
    }

    /**
     * Fluent builder for programmatic documents (headings, tables, charts,
     * barcodes...). Wrap the result with {@see render()} to apply the
     * configured fonts.
     */
    public function builder(): DocumentBuilder
    {
        return DocumentBuilder::new();
    }

    /**
     * Open an existing PDF form — a file path or the PDF bytes — to fill,
     * flatten and stamp it. The configured default font is used for values
     * the form's own fonts cannot show (Cyrillic, CJK, …).
     */
    public function fillForm(string $pdf, string $password = ''): ExistingFormFiller
    {
        $filler = str_starts_with(ltrim(substr($pdf, 0, 1024)), '%PDF-')
            ? ExistingFormFiller::fromBytes($pdf, $password)
            : ExistingFormFiller::fromFile($pdf, $password);

        $font = $this->config['fonts']['default'] ?? null;
        if (is_string($font) && $font !== '') {
            if (! is_readable($font)) {
                throw new \InvalidArgumentException("php-pdf font not readable: $font");
            }
            $filler->useFont($font);
        }

        return $filler;
    }

    /**
     * Wrap a native document so it renders with the configured engine.
     */
    public function render(Document $document): PendingPdf
    {
        return new PendingPdf($document, $this->engine(), $this);
    }

    /**
     * The configured signing setup (`php-pdf.signing`), with per-call
     * options: `reason`, `location`, `contact`, `signer_name`, `field`
     * (the signature field to sign), `certification` (a DocMdpPermission or
     * 1/2/3), `lock` (a FieldLock, 'all', or a list of field names),
     * `signer` (a Signer — a token, an HSM, an ExternalSigner), `timestamp`
     * (false to skip the configured TSA), `format` ('pades' | 'pkcs7'),
     * `signed_at`, `reserve` (bytes for the signature).
     *
     * @param  array<string, mixed>  $options
     */
    public function signatureConfig(array $options = []): SignatureConfig
    {
        $cfg = (array) ($this->config['signing'] ?? []);
        $pick = static fn (string $key): mixed => array_key_exists($key, $options) ? $options[$key] : ($cfg[$key] ?? null);

        $format = strtolower((string) $pick('format'));
        $certification = $options['certification'] ?? null;
        $lock = $options['lock'] ?? null;

        return new SignatureConfig(
            signerName: $pick('signer_name'),
            reason: $pick('reason'),
            location: $pick('location'),
            contactInfo: $pick('contact'),
            signedAt: $options['signed_at'] ?? null,
            fieldName: $options['field'] ?? null,
            signer: $options['signer'] ?? $this->configuredSigner(),
            subFilter: $format === 'pkcs7' ? SubFilter::Pkcs7Detached : SubFilter::CadesDetached,
            timestampAuthority: ($options['timestamp'] ?? true) === false ? null : $this->timestampAuthority(),
            contentsReserveBytes: $options['reserve'] ?? null,
            certification: $certification === null || $certification instanceof DocMdpPermission
                ? $certification
                : DocMdpPermission::from((int) $certification),
            lockFields: match (true) {
                $lock === null, $lock instanceof FieldLock => $lock,
                $lock === 'all' => FieldLock::all(),
                default => FieldLock::only(array_values((array) $lock)),
            },
        );
    }

    /** The configured timestamp authority, or null. */
    public function timestampAuthority(): ?TsaClient
    {
        $tsa = (array) ($this->config['signing']['timestamp'] ?? []);
        $url = $tsa['url'] ?? null;
        if (! is_string($url) || $url === '') {
            return null;
        }

        return new TsaClient(
            $url,
            transport: $this->httpTransport(),
            username: $tsa['username'] ?? null,
            password: $tsa['password'] ?? null,
            headers: (array) ($tsa['headers'] ?? []),
            policyOid: $tsa['policy'] ?? null,
            timeoutSeconds: (float) ($tsa['timeout'] ?? 15),
        );
    }

    /**
     * Sign an existing PDF — a file path or the bytes — or one signed
     * before: the signature is appended, earlier ones stay valid. Use the
     * returned PdfSigner to add a visible field, an empty field for a later
     * signer, or to prepare a signature for a key outside the server.
     */
    public function signer(string $pdf, string $password = ''): PdfSigner
    {
        return PdfSigner::fromBytes($this->pdfBytes($pdf), $password);
    }

    /**
     * Sign an existing PDF with the configured key. Without a `field`
     * option it goes into the first empty signature field, else into a new
     * invisible one. `appearance` (a SignatureAppearance) redraws the
     * stamp of the field signed, in the configured font.
     *
     * @param  array<string, mixed>|SignatureConfig  $options  See {@see signatureConfig()}.
     */
    public function sign(string $pdf, array|SignatureConfig $options = [], string $password = ''): string
    {
        $config = $options instanceof SignatureConfig ? $options : $this->signatureConfig($options);
        $signer = $this->signer($pdf, $password);
        $appearance = is_array($options) ? ($options['appearance'] ?? null) : null;
        if ($appearance instanceof SignatureAppearance) {
            $engine = $this->engine();
            $signer->withAppearance($appearance, $engine?->defaultFont, $engine?->boldFont);
        }

        return $signer->sign($config);
    }

    /**
     * Long-term validation for a signed PDF: certificates, OCSP responses
     * and CRLs of every signature go into the document (PAdES B-LT), and —
     * with a timestamp authority configured and `$archive` — a document
     * timestamp over it all (B-LTA).
     */
    public function ltv(string $pdf, bool $archive = true, string $password = ''): string
    {
        $http = $this->httpTransport();
        $signed = $this->signer($pdf, $password)->addValidationData(
            new OnlineRevocationSource(new OcspClient($http), new CrlFetcher($http)),
            issuers: new CertificateFetcher($http),
        );
        $tsa = $this->timestampAuthority();

        return $archive && $tsa !== null ? PdfSigner::fromBytes($signed, $password)->timestamp($tsa) : $signed;
    }

    /** Check every signature of a PDF against the configured trust anchors. */
    public function validate(string $pdf, string $password = ''): ValidationReport
    {
        $fetch = (bool) ($this->config['validation']['fetch_revocation'] ?? false);
        $http = $this->httpTransport();

        return SignatureValidator::fromBytes($this->pdfBytes($pdf), $password)->validate(
            $this->trustStore(),
            $fetch ? new OnlineRevocationSource(new OcspClient($http), new CrlFetcher($http)) : null,
        );
    }

    /** The configured trust anchors (`php-pdf.validation`). */
    public function trustStore(): TrustStore
    {
        $cfg = (array) ($this->config['validation'] ?? []);
        $store = ($cfg['system_trust'] ?? false) ? TrustStore::system() : new TrustStore;
        foreach ((array) ($cfg['trust'] ?? []) as $root) {
            foreach (TrustStore::fromPem((string) $root)->all() as $cert) {
                $store->add($cert);
            }
        }

        return $store;
    }

    /** php-pdf's HTTP (TSA, OCSP, CRL) through Laravel's client, under `signing.network`. */
    public function httpTransport(): LaravelHttpTransport
    {
        return new LaravelHttpTransport($this->http ?? new HttpFactory, $this->urlPolicy());
    }

    /** What signing may reach (`php-pdf.signing.network`). */
    public function urlPolicy(): UrlPolicy
    {
        return UrlPolicy::fromConfig((array) ($this->config['signing']['network'] ?? []));
    }

    /**
     * A detached CAdES signature (`.p7s`, DER) of a file or of bytes, with
     * the configured key and, if one is configured, a timestamp (CAdES-T).
     *
     * @param  array{signer?: Signer|CmsSigner, timestamp?: bool, signed_at?: \DateTimeInterface}  $options
     */
    public function signDetached(string $content, array $options = []): string
    {
        $signer = $options['signer'] ?? $this->configuredSigner();
        if ($signer instanceof CmsSigner && ! self::detachedTakesCmsSigner()) {
            throw new \LogicException('A detached signature with a CmsSigner (CryptoPro CSP, a signing service) needs dskripchenko/php-pdf 1.20 or later');
        }

        return DetachedSignature::sign(
            self::fileOrBytes($content),
            $signer,
            ($options['timestamp'] ?? true) === false ? null : $this->timestampAuthority(),
            $options['signed_at'] ?? null,
        );
    }

    /** Check a detached signature of a file or of bytes against the configured trust anchors. */
    public function verifyDetached(string $content, string $signature): SignatureReport
    {
        $fetch = (bool) ($this->config['validation']['fetch_revocation'] ?? false);
        $http = $this->httpTransport();

        return DetachedSignature::verify(
            self::fileOrBytes($content),
            self::fileOrBytes($signature),
            $this->trustStore(),
            $fetch ? new OnlineRevocationSource(new OcspClient($http), new CrlFetcher($http)) : null,
        );
    }

    /** The key from `php-pdf.signing`. */
    private function configuredSigner(): Signer|CmsSigner
    {
        $cfg = (array) ($this->config['signing'] ?? []);
        $bound = $cfg['signer'] ?? null;
        if (is_string($bound) && $bound !== '') {
            return $this->boundSigner ??= $this->resolveBinding($bound, 'signing.signer', [Signer::class, CmsSigner::class]);
        }
        $p12 = $cfg['pkcs12'] ?? null;
        if (is_string($p12) && $p12 !== '') {
            return OpenSslSigner::fromPkcs12(self::content($p12), (string) ($cfg['pkcs12_password'] ?? ''));
        }
        $cert = $cfg['certificate'] ?? null;
        $key = $cfg['key'] ?? null;
        if (! is_string($cert) || $cert === '' || ! is_string($key) || $key === '') {
            throw new \LogicException('No signing key configured: set php-pdf.signing certificate and key, or pkcs12');
        }

        return new OpenSslSigner(
            self::content($cert),
            self::content($key),
            $cfg['passphrase'] ?? null,
            array_map(static fn (mixed $c): string => self::content((string) $c), array_values((array) ($cfg['chain'] ?? []))),
        );
    }

    private function configuredFontProvider(mixed $binding): ?FontProvider
    {
        if (! is_string($binding) || $binding === '') {
            return null;
        }

        return $this->resolveBinding($binding, 'fonts.provider', [FontProvider::class]);
    }

    /**
     * A container binding or a class name from the config, checked against
     * the types it must have.
     *
     * @template T of object
     *
     * @param  list<class-string<T>>  $types
     * @return T
     */
    private function resolveBinding(string $id, string $key, array $types): object
    {
        $object = $this->container !== null
            ? $this->container->make($id)
            : (class_exists($id) ? new $id : throw new \LogicException("php-pdf.$key '$id' needs the Laravel container to resolve"));
        foreach ($types as $type) {
            if ($object instanceof $type) {
                return $object;
            }
        }

        throw new \LogicException(sprintf("php-pdf.%s '%s' resolved to %s, not a %s", $key, $id, get_debug_type($object), implode(' or ', $types)));
    }

    /** Whether the installed php-pdf signs detached with a CmsSigner (1.20+). */
    private static function detachedTakesCmsSigner(): bool
    {
        $type = (new \ReflectionMethod(DetachedSignature::class, 'sign'))->getParameters()[1]->getType();

        return $type !== null && str_contains((string) $type, 'CmsSigner');
    }

    /** A config value that is either the content itself or a file path. */
    private static function content(string $value): string
    {
        if (str_contains($value, '-----BEGIN') || ! is_file($value)) {
            return $value;
        }
        $content = file_get_contents($value);
        if ($content === false) {
            throw new \InvalidArgumentException("php-pdf: cannot read $value");
        }

        return $content;
    }

    /** A path to a readable file is read; anything else is the bytes themselves. */
    private static function fileOrBytes(string $value): string
    {
        $looksLikePath = $value !== '' && strlen($value) < 4096 && ! str_contains($value, "\0") && ! str_contains($value, "\n");

        return $looksLikePath && is_file($value) ? (string) file_get_contents($value) : $value;
    }

    private function pdfBytes(string $pdf): string
    {
        if (str_starts_with(ltrim(substr($pdf, 0, 1024)), '%PDF-')) {
            return $pdf;
        }
        $bytes = @file_get_contents($pdf);
        if ($bytes === false) {
            throw new \InvalidArgumentException("php-pdf: cannot read PDF $pdf");
        }

        return $bytes;
    }

    /**
     * The Engine assembled from config, or null when no fonts are
     * configured (base-14 defaults apply).
     */
    public function engine(): ?Engine
    {
        if ($this->engineBuilt) {
            return $this->engine;
        }
        $this->engineBuilt = true;

        $fonts = (array) ($this->config['fonts'] ?? []);
        $families = (array) ($fonts['families'] ?? []);
        $hasSingles = ($fonts['default'] ?? null) !== null;
        $provider = $this->configuredFontProvider($fonts['provider'] ?? null);

        if (! $hasSingles && $families === [] && $provider === null) {
            return $this->engine = null;
        }

        $load = static function (?string $path): ?PdfFont {
            if ($path === null || $path === '') {
                return null;
            }
            if (! is_readable($path)) {
                throw new \InvalidArgumentException("php-pdf font not readable: $path");
            }

            return new PdfFont(TtfFile::fromFile($path));
        };

        return $this->engine = new Engine(
            defaultFont: $load($fonts['default'] ?? null),
            boldFont: $load($fonts['bold'] ?? null),
            italicFont: $load($fonts['italic'] ?? null),
            boldItalicFont: $load($fonts['bold_italic'] ?? null),
            fontProvider: match (true) {
                $families === [] => $provider,
                $provider === null => new ConfigFontProvider($families),
                // The configured families first, then the provider.
                default => new ChainedFontProvider(new ConfigFontProvider($families), $provider),
            },
        );
    }

    public function pageSetup(): PageSetup
    {
        $paper = match (strtolower((string) ($this->config['paper'] ?? 'a4'))) {
            'a3' => PaperSize::A3,
            'a5' => PaperSize::A5,
            'a6' => PaperSize::A6,
            'letter' => PaperSize::Letter,
            'legal' => PaperSize::Legal,
            'tabloid' => PaperSize::Tabloid,
            'executive' => PaperSize::Executive,
            default => PaperSize::A4,
        };

        $margins = (array) ($this->config['margins'] ?? []);
        $defaults = new PageMargins;

        return new PageSetup(
            paperSize: $paper,
            orientation: strtolower((string) ($this->config['orientation'] ?? 'portrait')) === 'landscape'
                ? Orientation::Landscape
                : Orientation::Portrait,
            margins: new PageMargins(
                topPt: (float) ($margins['top'] ?? $defaults->topPt),
                rightPt: (float) ($margins['right'] ?? $defaults->rightPt),
                bottomPt: (float) ($margins['bottom'] ?? $defaults->bottomPt),
                leftPt: (float) ($margins['left'] ?? $defaults->leftPt),
            ),
        );
    }

    /**
     * @return array<string, string>
     */
    private function defaultMetadata(): array
    {
        return array_filter(
            (array) ($this->config['metadata'] ?? []),
            static fn ($v) => is_string($v) && $v !== '',
        );
    }
}
