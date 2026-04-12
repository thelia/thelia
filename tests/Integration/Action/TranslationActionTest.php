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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Translation\TranslationEvent;
use Thelia\Test\ActionIntegrationTestCase;

final class TranslationActionTest extends ActionIntegrationTestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir().'/thelia_translation_test_'.uniqid();
        mkdir($this->tmpDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
        parent::tearDown();
    }

    public function testGetTranslatableStringsExtractsFromPhpFiles(): void
    {
        file_put_contents(
            $this->tmpDir.'/Controller.php',
            <<<'PHP'
                <?php
                $translator->trans('Hello world');
                $translator->trans('Goodbye');
                PHP,
        );

        $event = TranslationEvent::createGetStringsEvent(
            $this->tmpDir,
            TranslationEvent::WALK_MODE_PHP,
            'en_US',
            'core',
        );

        $this->dispatch($event, TheliaEvents::TRANSLATION_GET_STRINGS);

        $strings = $event->getTranslatableStrings();
        self::assertSame(2, $event->getTranslatableStringCount());

        $texts = array_column($strings, 'text');
        self::assertContains('Hello world', $texts);
        self::assertContains('Goodbye', $texts);
    }

    public function testGetTranslatableStringsExtractsFromTemplateFiles(): void
    {
        file_put_contents(
            $this->tmpDir.'/page.html',
            '{intl l="Welcome to Thelia"}{intl l="Cart total"}',
        );

        $event = TranslationEvent::createGetStringsEvent(
            $this->tmpDir,
            TranslationEvent::WALK_MODE_TEMPLATE,
            'en_US',
            'frontOffice.default',
        );

        $this->dispatch($event, TheliaEvents::TRANSLATION_GET_STRINGS);

        $texts = array_column($event->getTranslatableStrings(), 'text');
        self::assertContains('Welcome to Thelia', $texts);
        self::assertContains('Cart total', $texts);
    }

    public function testWriteTranslationFileCreatesPhpFile(): void
    {
        $filePath = $this->tmpDir.'/translations.php';

        $event = TranslationEvent::createWriteFileEvent(
            $filePath,
            ['key1' => 'Hello', 'key2' => 'Goodbye'],
            ['key1' => 'Bonjour', 'key2' => 'Au revoir'],
            true,
        );
        // The writeFallbackFile listener also needs these:
        $event->setLocale('fr_FR');
        $event->setDomain('core');
        $event->setCustomFallbackStrings([]);
        $event->setGlobalFallbackStrings([]);

        $this->dispatch($event, TheliaEvents::TRANSLATION_WRITE_FILE);

        self::assertFileExists($filePath);

        $content = file_get_contents($filePath);
        self::assertStringContainsString("'Goodbye' => 'Au revoir'", $content);
        self::assertStringContainsString("'Hello' => 'Bonjour'", $content);
    }

    public function testGetTranslatableStringsReturnsEmptyForNonExistentDirectory(): void
    {
        $event = TranslationEvent::createGetStringsEvent(
            $this->tmpDir.'/does_not_exist',
            TranslationEvent::WALK_MODE_PHP,
            'en_US',
            'core',
        );

        $this->dispatch($event, TheliaEvents::TRANSLATION_GET_STRINGS);

        self::assertSame(0, $event->getTranslatableStringCount());
        self::assertEmpty($event->getTranslatableStrings());
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }
}
