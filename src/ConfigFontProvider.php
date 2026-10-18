<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf;

use Dskripchenko\PhpPdf\Font\FontProvider;
use Dskripchenko\PhpPdf\Font\Ttf\TtfFile;

/**
 * Resolves font families declared in config/php-pdf.php:
 *
 *     'families' => [
 *         'mono' => ['regular' => '/path/Mono.ttf', 'bold' => '/path/Mono-Bold.ttf'],
 *     ]
 *
 * php-pdf asks for a face as `mono-Bold`, `mono-Italic`, `mono-BoldItalic`
 * or `mono-Regular` before the bare `mono`; each maps to the `bold`,
 * `italic`, `bold_italic` and `regular` path. A face that is not configured
 * resolves to null, and php-pdf then tries the next one down to the regular
 * face. Family names are matched case-insensitively. Parsed fonts are cached
 * per request.
 */
class ConfigFontProvider implements FontProvider
{
    private const FACES = [
        '-bolditalic' => 'bold_italic',
        '-bold' => 'bold',
        '-italic' => 'italic',
        '-regular' => 'regular',
    ];

    /** @var array<string, ?TtfFile> */
    private array $cache = [];

    /**
     * @param  array<string, array<string, string>|string>  $families
     */
    public function __construct(private readonly array $families) {}

    public function resolve(string $name): ?TtfFile
    {
        $key = strtolower($name);
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = $this->load($key, $name);
    }

    private function load(string $key, string $name): ?TtfFile
    {
        $face = null;
        $familyKey = $key;
        foreach (self::FACES as $suffix => $configKey) {
            if (str_ends_with($key, $suffix) && ! $this->hasFamily($key)) {
                $face = $configKey;
                $familyKey = substr($key, 0, -strlen($suffix));
                break;
            }
        }

        foreach ($this->families as $family => $paths) {
            if (strtolower((string) $family) !== $familyKey) {
                continue;
            }
            $path = match (true) {
                ! is_array($paths) => $face === null || $face === 'regular' ? $paths : null,
                $face === null => $paths['regular'] ?? reset($paths),
                $face === 'bold_italic' => $paths['bold_italic'] ?? $paths['boldItalic'] ?? null,
                default => $paths[$face] ?? null,
            };
            if (! is_string($path) || $path === '') {
                return null;
            }
            if (! is_readable($path)) {
                throw new \InvalidArgumentException("php-pdf font family '$name' not readable: $path");
            }

            return TtfFile::fromFile($path);
        }

        return null;
    }

    /** A family whose own name ends like a face suffix ("Foo-Bold") wins. */
    private function hasFamily(string $key): bool
    {
        foreach (array_keys($this->families) as $family) {
            if (strtolower((string) $family) === $key) {
                return true;
            }
        }

        return false;
    }
}
