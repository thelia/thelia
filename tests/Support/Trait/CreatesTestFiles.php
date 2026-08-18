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

namespace Thelia\Tests\Support\Trait;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Provides helpers for tests that need temporary files
 * (image uploads, document uploads, etc.).
 *
 * Usage:
 *   use CreatesTestFiles;
 *   // then in tearDown: $this->cleanUpTestFiles();
 */
trait CreatesTestFiles
{
    private array $testFilePaths = [];

    protected function createTestPng(string $prefix = 'thelia_test_img_'): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), $prefix);
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tmpFile);
        imagedestroy($img);

        return $tmpFile;
    }

    protected function createTestSvg(string $prefix = 'thelia_test_svg_'): string
    {
        $path = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid($prefix).'.svg';
        file_put_contents(
            $path,
            '<?xml version="1.0" encoding="utf-8"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="120" height="40">'
            .'<rect width="120" height="40" fill="#000000"/></svg>',
        );

        return $path;
    }

    protected function createTestTextFile(string $content = 'test content', string $prefix = 'thelia_test_doc_'): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), $prefix);
        file_put_contents($tmpFile, $content);

        return $tmpFile;
    }

    protected function createUploadedFile(string $path, string $originalName, string $mimeType): UploadedFile
    {
        return new UploadedFile($path, $originalName, $mimeType, null, true);
    }

    protected function trackFileForCleanup(string $path): void
    {
        $this->testFilePaths[] = $path;
    }

    protected function cleanUpTestFiles(): void
    {
        foreach ($this->testFilePaths as $path) {
            @unlink($path);
        }
        $this->testFilePaths = [];
    }
}
