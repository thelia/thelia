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

namespace Thelia\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Tests\Support\Filesystem\TestVarCleaner;

final class TestVarCleanerTest extends TestCase
{
    /**
     * Only refusals are exercised here: no case may ever reach a real removal outside var/.
     *
     * @return iterable<string, array{string}>
     */
    public static function refusedPaths(): iterable
    {
        yield 'project root' => [THELIA_ROOT];
        yield 'project root without slash' => [rtrim(THELIA_ROOT, '/')];
        yield 'var itself' => [rtrim(THELIA_ROOT, '/').'/var'];
        yield 'var with slash' => [rtrim(THELIA_ROOT, '/').'/var/'];
        yield 'filesystem root' => ['/'];
        yield 'empty path' => [''];
        yield 'sibling of var' => [rtrim(THELIA_ROOT, '/').'/var-other/x'];
        yield 'escape by parent segments' => [rtrim(THELIA_ROOT, '/').'/var/../vendor'];
        yield 'escape up to the root' => [rtrim(THELIA_ROOT, '/').'/var/report-mail/../..'];
    }

    #[DataProvider('refusedPaths')]
    public function testAPathThatIsNotStrictlyBelowVarIsRefused(string $path): void
    {
        $this->expectException(\LogicException::class);

        TestVarCleaner::remove($path);
    }

    public function testAFolderBelowVarIsRemoved(): void
    {
        $directory = rtrim(THELIA_ROOT, '/').'/var/test-var-cleaner-'.bin2hex(random_bytes(6));
        mkdir($directory.'/nested', 0o777, true);
        file_put_contents($directory.'/nested/file.txt', 'x');

        TestVarCleaner::remove($directory);

        self::assertDirectoryDoesNotExist($directory);
    }
}
