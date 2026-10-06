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

namespace Thelia\Tests\Unit\Install;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Setup\CompiledDirectoryRemover;

/**
 * `setup/update.php` removes the caches of the previous release before it boots.
 * On a live shop they are written by the web server user, and the script runs as
 * another one that cannot delete them (#4055). The permissions of that situation
 * are reproduced with a read-only directory, which the current user cannot empty.
 */
final class CompiledDirectoryRemoverTest extends TestCase
{
    private string $root;

    public static function setUpBeforeClass(): void
    {
        require_once THELIA_SETUP_DIRECTORY.'CompiledDirectoryRemover.php';
    }

    protected function setUp(): void
    {
        if (\function_exists('posix_geteuid') && 0 === posix_geteuid()) {
            self::markTestSkipped('root ignores the permissions this test relies on.');
        }

        $this->root = sys_get_temp_dir().'/thelia-compiled-remover-'.bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->root.'/var/cache/prod/pools');
        file_put_contents($this->root.'/var/cache/prod/App_KernelProdContainer.php', '<?php');
        file_put_contents($this->root.'/var/cache/prod/pools/item', 'cached');
    }

    protected function tearDown(): void
    {
        if (!isset($this->root)) {
            return;
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($entries as $path => $entry) {
            if ($entry->isDir()) {
                chmod($path, 0o755);
            }
        }

        (new Filesystem())->remove($this->root);
    }

    public function testADirectoryTheUserOwnsIsDeleted(): void
    {
        $leftover = (new CompiledDirectoryRemover())->remove($this->root.'/var/cache/prod');

        self::assertNull($leftover);
        self::assertSame([], glob($this->root.'/var/cache/*'));
    }

    public function testFilesTheUserCannotDeleteAreMovedWhereTheKernelNeverLooks(): void
    {
        chmod($this->root.'/var/cache/prod', 0o555);

        $leftover = (new CompiledDirectoryRemover())->remove($this->root.'/var/cache/prod');

        self::assertFileDoesNotExist($this->root.'/var/cache/prod', 'The next boot would load the previous release.');
        self::assertNotNull($leftover);
        self::assertStringStartsWith($this->root.'/var/cache/prod.previous-', $leftover);
        self::assertFileExists($leftover.'/App_KernelProdContainer.php');
    }

    public function testADirectoryThatCannotBeMovedStopsTheUpdateAndLeavesItUntouched(): void
    {
        chmod($this->root.'/var/cache', 0o555);

        try {
            (new CompiledDirectoryRemover())->remove($this->root.'/var/cache/prod');
            self::fail('The kernel would boot on the caches of the previous release.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString($this->root.'/var/cache/prod', $exception->getMessage());
            self::assertStringContainsString(
                'sudo -u '.CompiledDirectoryRemover::ownerOf($this->root.'/var/cache/prod').' rm -rf',
                $exception->getMessage(),
            );
        }

        self::assertFileExists($this->root.'/var/cache/prod/App_KernelProdContainer.php');
        self::assertFileExists($this->root.'/var/cache/prod/pools/item');
    }
}
