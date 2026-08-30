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
use Thelia\Core\Install\CheckPermission;

/**
 * Guards the supported PHP range gate: minimum inclusive (8.3), maximum
 * exclusive (9.0). The previous condition only ever rejected versions that
 * were both below the minimum and below the maximum, so every version at or
 * above the minimum passed, including untested future majors.
 */
final class CheckPermissionPhpVersionTest extends TestCase
{
    public static function phpVersions(): array
    {
        return [
            'below the minimum is rejected' => ['8.2.30', false],
            'the minimum is accepted' => ['8.3.0', true],
            'a patch of the minimum is accepted' => ['8.3.20', true],
            'a tested minor is accepted' => ['8.4.10', true],
            'the highest tested minor is accepted' => ['8.5.0', true],
            'the next major is rejected' => ['9.0.0', false],
        ];
    }

    #[DataProvider('phpVersions')]
    public function testPhpVersionGate(string $phpVersion, bool $expectedSupported): void
    {
        // Bypass the constructor: it runs the full permission check (filesystem,
        // extensions) which is irrelevant to the version range under test.
        $check = (new \ReflectionClass(CheckPermission::class))->newInstanceWithoutConstructor();

        self::assertSame($expectedSupported, $check->isPhpVersionSupported($phpVersion));
    }
}
