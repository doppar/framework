<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class UriMigrationTest extends TestCase
{
    public function testLenientParsingIsLimitedToRequestAndCompatibilityFallbacks(): void
    {
        $sourceDirectory = dirname(__DIR__) . '/src';
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS)
        );
        $parseUrlCalls = [];

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all('/\bparse_url\s*\(/', $contents, $matches);

            if ($matches[0] !== []) {
                $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($sourceDirectory) + 1));
                $parseUrlCalls[$relativePath] = count($matches[0]);
            }
        }

        ksort($parseUrlCalls);

        $this->assertSame([
            'Phaseolies/Http/Request.php' => 2,
            'Phaseolies/Http/Response/RedirectResponse.php' => 1,
            'Phaseolies/Utilities/Paginator.php' => 1,
        ], $parseUrlCalls);
    }
}
