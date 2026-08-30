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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Core\Install\Update;

/**
 * Thelia 3 ships no update script between 2.5.5 and 3.0.0-alpha1, so its updater
 * cannot bridge a Thelia 2 database: treated as 2.5.5, a 2.6 schema would have the
 * 3.0 migrations replayed over it. `Update::isVersionUpdatable()` is the guard that
 * tells a Thelia 2 database apart from one already on the 3.x line.
 */
final class UpdateMinimumVersionTest extends TestCase
{
    #[DataProvider('versionProvider')]
    public function testOnlyThelia3DatabasesCanBeUpdatedInPlace(string $currentVersion, bool $updatable): void
    {
        self::assertSame($updatable, Update::isVersionUpdatable($currentVersion));
    }

    public static function versionProvider(): iterable
    {
        // Thelia 2 databases: refused.
        yield 'the 2.6 line the jump skips' => ['2.6.2', false];
        yield 'the last 2.5 the scripts stop at' => ['2.5.5', false];
        yield 'an early 2.x' => ['2.0.0', false];
        yield 'an empty marker is not a 3.x version' => ['', false];

        // Thelia 3 databases: accepted.
        yield 'the first 3.0 script, the boundary itself' => ['3.0.0-alpha1', true];
        yield 'a 3.0 beta' => ['3.0.0-beta5', true];
        yield 'the stable 3.0.0' => ['3.0.0', true];
        yield 'a later 3.x' => ['3.1.0', true];
    }
}
