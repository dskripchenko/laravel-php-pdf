<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf\Signing;

/** A URL the signing {@see UrlPolicy} refuses to reach. */
final class UrlNotAllowed extends \RuntimeException {}
