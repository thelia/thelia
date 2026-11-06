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

namespace Thelia\Tests\Unit\Core\Template\Assets;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Template\Assets\AssetManager;
use Thelia\Log\Tlog;

/**
 * An asset a module ships (module_asset() in Twig) is copied to the web directory. Outside debug
 * mode, the copy follows its source: after a module update, the page serves the new file, not the
 * one the previous version published.
 */
final class AssetManagerTest extends TestCase
{
    private string $directory;

    private ?Tlog $logger = null;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/thelia-asset-'.bin2hex(random_bytes(4));
        mkdir($this->directory.'/module/assets', 0o777, true);

        // The asset manager logs what it copies; the shared logger reads its settings from the
        // database, which a unit test has not. A silent one takes its place for the test.
        $instance = new \ReflectionProperty(Tlog::class, 'instance');
        $this->logger = $instance->getValue();
        $instance->setValue(null, (new \ReflectionClass(Tlog::class))->newInstanceWithoutConstructor()->setLevel(Tlog::MUET));
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Tlog::class, 'instance'))->setValue(null, $this->logger);
        (new Filesystem())->remove($this->directory);
    }

    public function testTheFirstPagePublishesTheAsset(): void
    {
        $source = $this->source('console.log("1.0.0");', '2026-11-06 10:00:00');

        $url = $this->process();

        self::assertSame('/assets/backOffice/default/MyModule/assets/editor.js', $url);
        self::assertFileEquals($source, $this->published());
    }

    public function testACopyOfThePreviousVersionIsReplaced(): void
    {
        $this->source('console.log("1.0.0");', '2026-10-30 10:00:00');
        $this->process();

        $source = $this->source('console.log("1.1.0");', '2026-11-06 10:00:00');
        $this->process();

        self::assertFileEquals($source, $this->published());
    }

    public function testACopyOfTheSameSizeIsReplacedWhenItsDateDiffers(): void
    {
        $this->source('a{color:red}', '2026-11-06 10:00:00');
        $this->process();

        $source = $this->source('a{color:tan}', '2026-10-30 10:00:00');
        $this->process();

        self::assertFileEquals($source, $this->published(), 'A source older than its copy, as an archive may restore it, counts as well.');
    }

    public function testAnUpToDateCopyIsNotWrittenAgain(): void
    {
        $this->source('console.log("1.0.0");', '2026-11-06 10:00:00');
        $this->process();
        file_put_contents($this->published(), 'console.log("x.y.z");');
        touch($this->published(), (int) strtotime('2026-11-06 10:00:00'));

        $this->process();

        self::assertStringEqualsFile($this->published(), 'console.log("x.y.z");', 'Same size, same date: nothing is copied.');
    }

    private function source(string $content, string $date): string
    {
        $path = $this->directory.'/module/assets/editor.js';
        file_put_contents($path, $content);
        touch($path, (int) strtotime($date));
        clearstatcache();

        return $path;
    }

    private function published(): string
    {
        return $this->directory.'/web/backOffice/default/MyModule/assets/editor.js';
    }

    private function process(): string
    {
        return (new AssetManager(false))->processAsset(
            $this->directory.'/module/assets/editor.js',
            $this->directory.'/module',
            $this->directory.'/web',
            'backOffice/default',
            'MyModule',
            '/assets',
            'js',
            [],
            false,
        );
    }
}
