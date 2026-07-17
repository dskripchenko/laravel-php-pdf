<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelPhpPdf\Tests;

use Dskripchenko\LaravelPhpPdf\PhpPdfServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PhpPdfServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Pdf' => \Dskripchenko\LaravelPhpPdf\Facades\Pdf::class];
    }
}
