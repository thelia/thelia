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

namespace Thelia\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Core\Security\AccessManager;

/**
 * AccessManager encodes the CRUD permissions as a 4-bit bitmask:
 *   bit 3 = VIEW, bit 2 = CREATE, bit 1 = UPDATE, bit 0 = DELETE.
 * The tests below pin that contract.
 */
final class AccessManagerTest extends TestCase
{
    public function testNoAccessGrantsNothing(): void
    {
        $manager = new AccessManager(0);

        self::assertFalse($manager->can(AccessManager::VIEW));
        self::assertFalse($manager->can(AccessManager::CREATE));
        self::assertFalse($manager->can(AccessManager::UPDATE));
        self::assertFalse($manager->can(AccessManager::DELETE));
    }

    public function testFullAccessValueGrantsEverything(): void
    {
        $manager = new AccessManager(AccessManager::getMaxAccessValue());

        self::assertTrue($manager->can(AccessManager::VIEW));
        self::assertTrue($manager->can(AccessManager::CREATE));
        self::assertTrue($manager->can(AccessManager::UPDATE));
        self::assertTrue($manager->can(AccessManager::DELETE));
    }

    public function testGetMaxAccessValueIsTheSumOfAllBits(): void
    {
        // View(8) + Create(4) + Update(2) + Delete(1) = 15
        self::assertSame(15, AccessManager::getMaxAccessValue());
    }

    #[DataProvider('singleBitAccessValues')]
    public function testIndividualPermissionMapsToItsSingleBit(int $value, string $granted): void
    {
        $manager = new AccessManager($value);

        foreach ([AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE] as $permission) {
            self::assertSame(
                $permission === $granted,
                $manager->can($permission),
                \sprintf('Value %d should grant only %s, found %s', $value, $granted, $permission),
            );
        }
    }

    public function testUnknownPermissionAlwaysFails(): void
    {
        $manager = new AccessManager(AccessManager::getMaxAccessValue());

        self::assertFalse($manager->can('IMAGINARY'));
    }

    public function testBuildRecomputesAccessValueFromPermissionList(): void
    {
        $manager = new AccessManager(0);
        $manager->build([AccessManager::VIEW, AccessManager::UPDATE]);

        self::assertTrue($manager->can(AccessManager::VIEW));
        self::assertFalse($manager->can(AccessManager::CREATE));
        self::assertTrue($manager->can(AccessManager::UPDATE));
        self::assertFalse($manager->can(AccessManager::DELETE));
        self::assertSame((2 ** 3) + (2 ** 1), $manager->getAccessValue());
    }

    public function testBuildIgnoresUnknownPermissionsSilently(): void
    {
        $manager = new AccessManager(0);
        $manager->build([AccessManager::VIEW, 'UNKNOWN']);

        self::assertTrue($manager->can(AccessManager::VIEW));
        self::assertSame(2 ** 3, $manager->getAccessValue());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function singleBitAccessValues(): iterable
    {
        yield 'only VIEW' => [2 ** 3, AccessManager::VIEW];
        yield 'only CREATE' => [2 ** 2, AccessManager::CREATE];
        yield 'only UPDATE' => [2 ** 1, AccessManager::UPDATE];
        yield 'only DELETE' => [2 ** 0, AccessManager::DELETE];
    }
}
