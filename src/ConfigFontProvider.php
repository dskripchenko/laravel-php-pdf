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
 * Family names are matched case-insensitively. Parsed fonts are cached per
 * request.
 */
class ConfigFontProvider implements FontProvider
{
    /** @var array<string, TtfFile> */
    private array $cache = [];

    /**
     * @param  array<string, array<string, string>|string>  $families
     */
    public function __construct(private readonly array $families) {}

    public function resolve(string $name): ?TtfFile
    {
        $key = strtolower($name);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        foreach ($this->families as $family => $paths) {
            if (strtolower((string) $family) !== $key) {
                continue;
            }
            $path = is_array($paths) ? ($paths['regular'] ?? reset($paths)) : $paths;
            if (! is_string($path) || $path === '') {
                return null;
            }
            if (! is_readable($path)) {
                throw new \InvalidArgumentException("php-pdf font family '$name' not readable: $path");
            }

            return $this->cache[$key] = TtfFile::fromFile($path);
        }

        return null;
    }
}
