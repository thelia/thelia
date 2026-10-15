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

namespace Thelia\Tests\Unit\Domain\Pricing;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Pricing\CatalogPriceResolverInterface;
use Thelia\Domain\Pricing\EffectivePrice;
use Thelia\Domain\Pricing\EffectivePriceResolver;
use Thelia\Domain\Pricing\ResolvedCatalogPrice;
use Thelia\Domain\Sale\ReservedPrice;
use Thelia\Domain\Sale\ReservedSalePriceResolver;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Model\Currency;
use Thelia\Model\Customer;

final class EffectivePriceResolverTest extends TestCase
{
    private Currency $currency;

    private Customer $customer;

    protected function setUp(): void
    {
        $this->currency = new Currency();
        $this->currency->setId(1);
        $this->customer = new Customer();
        $this->customer->setId(42);
    }

    public function testTheCollaboratorsAreAskedForEachSaleElementOnceAsIntegersAtTheGivenInstant(): void
    {
        $now = new \DateTimeImmutable('-1 hour');
        $catalog = $this->createMock(CatalogPriceResolverInterface::class);
        $catalog->expects(self::once())
            ->method('resolve')
            ->with(self::identicalTo([9, 7, 3]), $this->currency, $this->customer, self::identicalTo($now))
            ->willReturn([]);
        $reserved = $this->createMock(ReservedSalePriceResolver::class);
        $reserved->expects(self::once())
            ->method('resolve')
            ->with(self::identicalTo([9, 7, 3]), $this->currency, $this->customer, self::identicalTo($now))
            ->willReturn([]);

        $resolver = new EffectivePriceResolver($catalog, $reserved, $this->saleAudience(true));

        self::assertSame([], $resolver->resolve(['9', 7, 9, 3], $this->currency, $this->customer, $now));
    }

    public function testAGuestIsNeverPricedByAReservedSale(): void
    {
        $reserved = $this->createMock(ReservedSalePriceResolver::class);
        $reserved->expects(self::never())->method('resolve');

        $resolver = new EffectivePriceResolver($this->catalogAnswering([]), $reserved, $this->saleAudience(true));

        self::assertSame([], $resolver->resolve([5], $this->currency, null));
    }

    public function testTheReservedSalesAreNotLookedUpWhileNoneIsRunning(): void
    {
        $reserved = $this->createMock(ReservedSalePriceResolver::class);
        $reserved->expects(self::never())->method('resolve');

        $resolver = new EffectivePriceResolver($this->catalogAnswering([]), $reserved, $this->saleAudience(false));

        self::assertSame([], $resolver->resolve([5], $this->currency, $this->customer));
    }

    public function testAReservedPriceAppliesWhereNoRulePrices(): void
    {
        $endsAt = new \DateTimeImmutable('+3 days');
        $resolver = $this->resolverWith([], [5 => new ReservedPrice(5, 40.0, 12, $endsAt, true)]);

        $price = $resolver->resolve([5], $this->currency, $this->customer)[5];

        self::assertEquals(new EffectivePrice(5, 40.0, true, EffectivePrice::SOURCE_RESERVED_SALE, null, 12, $endsAt), $price);
    }

    public function testAReservedPriceBelowTheRulePriceWins(): void
    {
        $resolver = $this->resolverWith(
            [5 => new ResolvedCatalogPrice(5, 50.0, 3, false)],
            [5 => new ReservedPrice(5, 49.99, 12, null, false)],
        );

        $price = $resolver->resolve([5], $this->currency, $this->customer)[5];

        self::assertSame(EffectivePrice::SOURCE_RESERVED_SALE, $price->source);
        self::assertSame(49.99, $price->untaxedPromoPrice);
    }

    public function testAReservedPriceEqualToTheRulePriceLeavesTheRuleInCharge(): void
    {
        $validUntil = new \DateTimeImmutable('+2 days');
        $resolver = $this->resolverWith(
            [5 => new ResolvedCatalogPrice(5, 50.0, 3, true, $validUntil)],
            [5 => new ReservedPrice(5, 50.0, 12, null, false)],
        );

        $price = $resolver->resolve([5], $this->currency, $this->customer)[5];

        self::assertEquals(new EffectivePrice(5, 50.0, true, EffectivePrice::SOURCE_RULE, 3, null, $validUntil), $price);
    }

    public function testAReservedPriceAboveTheRulePriceLeavesTheRuleInCharge(): void
    {
        $resolver = $this->resolverWith(
            [5 => new ResolvedCatalogPrice(5, 50.0, 3, false)],
            [5 => new ReservedPrice(5, 60.0, 12, null, false)],
        );

        $price = $resolver->resolve([5], $this->currency, $this->customer)[5];

        self::assertSame(EffectivePrice::SOURCE_RULE, $price->source);
        self::assertSame(50.0, $price->untaxedPromoPrice);
    }

    public function testEverySaleElementSomethingPricesIsAnswered(): void
    {
        $resolver = $this->resolverWith(
            [6 => new ResolvedCatalogPrice(6, 50.0, 3, false)],
            [5 => new ReservedPrice(5, 30.0, 12, null, false)],
        );

        $prices = $resolver->resolve([5, 6, 7], $this->currency, $this->customer);

        self::assertSame([5, 6], array_keys($prices));
        self::assertSame(EffectivePrice::SOURCE_RESERVED_SALE, $prices[5]->source);
        self::assertSame(EffectivePrice::SOURCE_RULE, $prices[6]->source);
    }

    /**
     * @param array<int, ResolvedCatalogPrice> $rulePrices
     * @param array<int, ReservedPrice>        $reservedPrices
     */
    private function resolverWith(array $rulePrices, array $reservedPrices): EffectivePriceResolver
    {
        $reserved = $this->createStub(ReservedSalePriceResolver::class);
        $reserved->method('resolve')->willReturn($reservedPrices);

        return new EffectivePriceResolver($this->catalogAnswering($rulePrices), $reserved, $this->saleAudience(true));
    }

    /**
     * @param array<int, ResolvedCatalogPrice> $rulePrices
     */
    private function catalogAnswering(array $rulePrices): CatalogPriceResolverInterface
    {
        $catalog = $this->createStub(CatalogPriceResolverInterface::class);
        $catalog->method('resolve')->willReturn($rulePrices);

        return $catalog;
    }

    private function saleAudience(bool $hasActiveReservedSale): SaleAudienceChecker
    {
        $checker = $this->createStub(SaleAudienceChecker::class);
        $checker->method('hasActiveReservedSale')->willReturn($hasActiveReservedSale);

        return $checker;
    }
}
