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

namespace Thelia\Tests\Unit\Domain\Sale;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Catalog\Product\ProductVisibilityRuleInterface;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Domain\Sale\ReservedSaleProductRule;
use Thelia\Domain\Sale\ReservedSaleVisibility;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Model\Customer;

/**
 * The callers that still read the catalog through ReservedSaleVisibility (the
 * loops, the product view, the sale elements of a product, the theme sitemap) get
 * every rule ProductVisibility applies, and a caller that builds the class with
 * its first two arguments keeps the reserved operations.
 */
final class ReservedSaleVisibilityDelegationTest extends TestCase
{
    public function testTheCatalogIsReadThroughProductVisibility(): void
    {
        $saleAudienceChecker = $this->saleAudienceChecker();
        $currentCustomerProvider = $this->createStub(CurrentCustomerProvider::class);
        $reservedSaleRule = new ReservedSaleProductRule($saleAudienceChecker, $currentCustomerProvider);
        $moduleRule = new class implements ProductVisibilityRuleInterface {
            public function visibleProductClause(string $productIdColumn, ?Customer $customer): ?string
            {
                return \sprintf('%s <> 3', $productIdColumn);
            }
        };

        $visibility = new ReservedSaleVisibility(
            $saleAudienceChecker,
            $currentCustomerProvider,
            new ProductVisibility($reservedSaleRule, $currentCustomerProvider, [$moduleRule]),
            $reservedSaleRule,
        );

        self::assertSame('(product.id <> 3)', $visibility->visibleProductClause('product.id'));
    }

    public function testBuiltWithItsFirstTwoArgumentsItStillAnswersForTheReservedOperations(): void
    {
        $visibility = new ReservedSaleVisibility($this->saleAudienceChecker(), $this->createStub(CurrentCustomerProvider::class));

        self::assertNull($visibility->visibleProductClause('product.id'));
        self::assertSame([], $visibility->hiddenSaleIds());
        self::assertSame([], $visibility->entitledHidingSaleIds());
    }

    /**
     * The memory of the private drops lives in the rule now: resetting the class
     * still empties it, as the workers and the tests expect.
     */
    public function testResettingItResetsTheRuleOfTheReservedOperations(): void
    {
        $reservedSaleRule = $this->createMock(ReservedSaleProductRule::class);
        $reservedSaleRule->expects(self::once())->method('reset');

        $currentCustomerProvider = $this->createStub(CurrentCustomerProvider::class);

        $visibility = new ReservedSaleVisibility(
            $this->saleAudienceChecker(),
            $currentCustomerProvider,
            new ProductVisibility($reservedSaleRule, $currentCustomerProvider, []),
            $reservedSaleRule,
        );
        $visibility->reset();
    }

    /**
     * Given one and not the other, the class would hold a second memory of the
     * private drops that reset() never empties: it refuses.
     */
    public function testItRefusesTheVisibilityWithoutTheRuleItHolds(): void
    {
        $currentCustomerProvider = $this->createStub(CurrentCustomerProvider::class);
        $reservedSaleRule = $this->createStub(ReservedSaleProductRule::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('both its ProductVisibility and its ReservedSaleProductRule, or neither');

        new ReservedSaleVisibility(
            $this->saleAudienceChecker(),
            $currentCustomerProvider,
            new ProductVisibility($reservedSaleRule, $currentCustomerProvider, []),
        );
    }

    public function testItRefusesTheRuleWithoutTheVisibilityThatReadsIt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('both its ProductVisibility and its ReservedSaleProductRule, or neither');

        new ReservedSaleVisibility(
            $this->saleAudienceChecker(),
            $this->createStub(CurrentCustomerProvider::class),
            reservedSaleProductRule: $this->createStub(ReservedSaleProductRule::class),
        );
    }

    private function saleAudienceChecker(): SaleAudienceChecker
    {
        $saleAudienceChecker = $this->createStub(SaleAudienceChecker::class);
        $saleAudienceChecker->method('hasActiveReservedSale')->willReturn(false);

        return $saleAudienceChecker;
    }
}
