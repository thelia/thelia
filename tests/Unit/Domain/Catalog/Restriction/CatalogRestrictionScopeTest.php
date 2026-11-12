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

namespace Thelia\Tests\Unit\Domain\Catalog\Restriction;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Catalog\Restriction\CatalogRestrictionMode;
use Thelia\Domain\Catalog\Restriction\CatalogRestrictionScope;

final class CatalogRestrictionScopeTest extends TestCase
{
    public function testAScopeWithNothingInItBindsNobody(): void
    {
        $scope = new CatalogRestrictionScope();

        self::assertTrue($scope->isEmpty());
        self::assertFalse($scope->hasAllowList());
    }

    /**
     * @return iterable<string, array{CatalogRestrictionScope, bool, bool}>
     */
    public static function scopes(): iterable
    {
        yield 'allowed category' => [new CatalogRestrictionScope(allowedCategoryIds: [1]), true, false];
        yield 'allowed brand' => [new CatalogRestrictionScope(allowedBrandIds: [1]), true, false];
        yield 'allowed product' => [new CatalogRestrictionScope(allowedProductIds: [1]), true, false];
        yield 'denied category only' => [new CatalogRestrictionScope(deniedCategoryIds: [1]), false, false];
        yield 'denied brand only' => [new CatalogRestrictionScope(deniedBrandIds: [1]), false, false];
        yield 'denied product only' => [new CatalogRestrictionScope(deniedProductIds: [1]), false, false];
    }

    #[DataProvider('scopes')]
    public function testOnlyAnAllowListClosesWhatItDoesNotOpen(CatalogRestrictionScope $scope, bool $hasAllowList, bool $isEmpty): void
    {
        self::assertSame($hasAllowList, $scope->hasAllowList());
        self::assertSame($isEmpty, $scope->isEmpty());
    }

    public function testTheModesAreTheValuesOfTheColumn(): void
    {
        self::assertSame('allow', CatalogRestrictionMode::Allow->value);
        self::assertSame('deny', CatalogRestrictionMode::Deny->value);
        self::assertNull(CatalogRestrictionMode::tryFrom('block'));
    }
}
