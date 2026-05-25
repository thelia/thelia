<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Command\Import;

use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractDemoImporter implements DemoImporterInterface
{
    /**
     * Streams a semicolon-separated demo CSV row by row. Blank lines are
     * skipped; the header is skipped by default.
     *
     * @return \Generator<int, list<string>>
     */
    protected function readCsv(string $path, bool $skipHeader = true): \Generator
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Missing demo data file: '.$path);
        }

        $handle = fopen($path, 'r');
        if (false === $handle) {
            throw new \RuntimeException('Unable to open demo data file: '.$path);
        }

        try {
            $rowNumber = 0;
            while (false !== ($row = fgetcsv($handle, null, ';'))) {
                if ([null] === $row) {
                    continue;
                }

                if ($skipHeader && 0 === $rowNumber++) {
                    continue;
                }

                /* @var list<string> $row */
                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Copies a demo image into the public media directory for the given type
     * (product, brand, category...). Missing source files are silently ignored.
     */
    protected function copyImage(DemoImportContext $context, string $imageName, string $imageType): void
    {
        $source = $context->imagesDir.$imageName;
        if (!is_file($source)) {
            return;
        }

        (new Filesystem())->copy($source, THELIA_LOCAL_DIR.'media/images/'.$imageType.'/'.$imageName, true);
    }
}
