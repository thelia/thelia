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

namespace Thelia\Tests\Unit\Domain\Pricing\PriceList;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivity;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\CustomerPrice\CustomerGroupPriceLoader;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContext;
use Thelia\Domain\Pricing\CustomerPrice\ResolvedPrice;
use Thelia\Domain\Pricing\EffectivePrice;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\Pricing\PriceList\PriceListMatch;
use Thelia\Domain\Pricing\PriceList\PriceListResolverInterface;
use Thelia\Domain\Pricing\Rule\Storage\BasePriceLoader;
use Thelia\Domain\Pricing\Rule\Storage\BasePrices;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Tests\Support\Pricing\LoadedCustomerDiscountRate;

/**
 * The price lists come first in the price of a customer, and what they price is final.
 *
 * Catalog price 100, a catalog rule at -30 %, a group at -10 %, a customer at -5 %: the
 * chain alone gives 66.5 (rule and discounts compete), and a price list line gives its
 * own price, whatever the rest says.
 */
final class CustomerPriceContextPriceListTest extends TestCase
{
    private const PSE = 12;

    private Currency $currency;

    protected function setUp(): void
    {
        $this->currency = new Currency();
        $this->currency->setId(1);
    }

    public function testAPriceListLineIsTheFinalPriceWhateverTheRuleTheGroupAndTheCustomerSay(): void
    {
        $price = $this->resolveOne($this->context(new PriceListMatch(7, 'PRO', '80.000000', true, 1, [['minQuantity' => 1, 'untaxedPrice' => '80.000000']])));

        self::assertEqualsWithDelta(80.0, $price->untaxedPrice, 1e-9);
        self::assertEqualsWithDelta(80.0, $price->untaxedPromoPrice, 1e-9);
        self::assertEqualsWithDelta(80.0, $price->chargedPrice(), 1e-9);
        self::assertFalse($price->isPromo);
        self::assertFalse($price->displayInitialPrice);
        self::assertSame(ResolvedPrice::SOURCE_PRICE_LIST, $price->source);
        self::assertSame(0.0, $price->customerDiscountRate);
        self::assertSame(0.0, $price->customerGroupDiscountRate);
        self::assertTrue($price->purchasable);
        self::assertSame(7, $price->priceListId);
        self::assertSame([['minQuantity' => 1, 'untaxedPrice' => '80.000000']], $price->tiers);
    }

    public function testASaleElementNoListPricesFollowsTheChainOfTheCatalog(): void
    {
        $price = $this->resolveOne($this->context(null));

        // 100 less 10 % (group), less 5 % (customer), the rule at -30 % being the lower: 70 less 5 %.
        self::assertEqualsWithDelta(66.5, $price->untaxedPromoPrice, 1e-9);
        self::assertSame(EffectivePrice::SOURCE_RULE, $price->source);
        self::assertTrue($price->purchasable);
        self::assertNull($price->priceListId);
        self::assertSame([], $price->tiers);
    }

    public function testAnExclusiveListThatClosesTheSaleElementLeavesThePriceAndRefusesTheSale(): void
    {
        $chain = $this->resolveOne($this->context(null));
        $closed = $this->resolveOne($this->context(new PriceListMatch(9, 'EXCL', null, false, 0, [])));

        self::assertFalse($closed->purchasable);
        self::assertSame(9, $closed->priceListId);
        self::assertSame($chain->untaxedPrice, $closed->untaxedPrice);
        self::assertSame($chain->untaxedPromoPrice, $closed->untaxedPromoPrice);
        self::assertSame($chain->source, $closed->source);
        self::assertSame([], $closed->tiers);
    }

    public function testTheQuantitiesAreHandedToTheResolverAndTheCatalogIsNotAskedForWhatAListPrices(): void
    {
        $resolver = $this->createMock(PriceListResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')
            ->with([self::PSE], $this->currency, self::isInstanceOf(Customer::class), [self::PSE => 10])
            ->willReturn([self::PSE => new PriceListMatch(7, 'PRO', '9.000000', true, 10, [])]);
        $basePriceLoader = $this->createMock(BasePriceLoader::class);
        $basePriceLoader->expects(self::never())->method('load');

        $context = new CustomerPriceContext(
            $basePriceLoader,
            $this->createStub(CustomerGroupPriceLoader::class),
            $this->createStub(EffectivePriceCatalog::class),
            $this->probe(),
            $this->groupResolver(),
            new LoadedCustomerDiscountRate(),
            priceListResolver: $resolver,
        );

        $prices = $context->resolve([self::PSE], $this->currency, $this->customer(), [self::PSE => 10]);

        self::assertEqualsWithDelta(9.0, $prices[self::PSE]->untaxedPrice, 1e-9);
    }

    public function testWithoutAResolverTheChainIsAlone(): void
    {
        $context = new CustomerPriceContext(
            $this->baseLoader(),
            $this->createStub(CustomerGroupPriceLoader::class),
            $this->createStub(EffectivePriceCatalog::class),
            $this->probe(),
            $this->groupResolver(),
            new LoadedCustomerDiscountRate(),
        );

        $price = $context->resolve([self::PSE], $this->currency, $this->customer())[self::PSE];

        self::assertEqualsWithDelta(85.5, $price->untaxedPrice, 1e-9);
        self::assertTrue($price->purchasable);
    }

    private function resolveOne(CustomerPriceContext $context): ResolvedPrice
    {
        $prices = $context->resolve([self::PSE], $this->currency, $this->customer());
        self::assertArrayHasKey(self::PSE, $prices);

        return $prices[self::PSE];
    }

    private function context(?PriceListMatch $match): CustomerPriceContext
    {
        $resolver = $this->createStub(PriceListResolverInterface::class);
        $resolver->method('resolve')->willReturn(null === $match ? [] : [self::PSE => $match]);
        $catalog = $this->createStub(EffectivePriceCatalog::class);
        // The catalog rule: -30 % on the catalog price, as the engine would answer.
        $catalog->method('warm')->willReturn([self::PSE => new EffectivePrice(self::PSE, 70.0, true, EffectivePrice::SOURCE_RULE)]);

        return new CustomerPriceContext(
            $this->baseLoader(),
            $this->createStub(CustomerGroupPriceLoader::class),
            $catalog,
            $this->probe(),
            $this->groupResolver(),
            new LoadedCustomerDiscountRate(),
            priceListResolver: $resolver,
        );
    }

    private function baseLoader(): BasePriceLoader
    {
        $basePriceLoader = $this->createStub(BasePriceLoader::class);
        $basePriceLoader->method('load')->willReturn([self::PSE => new BasePrices(self::PSE, 1, [1 => 100.0], [1 => 100.0], false)]);

        return $basePriceLoader;
    }

    private function probe(): CatalogActivityProbe
    {
        $probe = $this->createStub(CatalogActivityProbe::class);
        $probe->method('read')->willReturn(new CatalogActivity(false, false, false, false, true, false, 1, true, true));

        return $probe;
    }

    private function groupResolver(): CustomerGroupResolver
    {
        $resolver = $this->createStub(CustomerGroupResolver::class);
        $resolver->method('groupOf')->willReturnCallback(static fn (?Customer $customer): CustomerGroup => $customer?->getCustomerGroup() ?? new CustomerGroup());
        $resolver->method('groupIdOf')->willReturn(5);
        $resolver->method('defaultGroupId')->willReturn(1);

        return $resolver;
    }

    private function customer(): Customer
    {
        $group = new CustomerGroup();
        $group->setId(5);
        $group->setDiscount('10');
        $group->setDisplayTaxedPrice(1);

        $customer = new class($group) extends Customer {
            public function __construct(private readonly CustomerGroup $group)
            {
                parent::__construct();
            }

            public function getCustomerGroup(?ConnectionInterface $con = null): CustomerGroup
            {
                return $this->group;
            }
        };
        $customer->setId(42);
        $customer->setCustomerGroupId(5);
        $customer->setDiscount('5');

        return $customer;
    }
}
