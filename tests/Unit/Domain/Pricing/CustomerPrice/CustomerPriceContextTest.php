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

namespace Thelia\Tests\Unit\Domain\Pricing\CustomerPrice;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivity;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\CustomerPrice\CustomerGroupPrice;
use Thelia\Domain\Pricing\CustomerPrice\CustomerGroupPriceLoader;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContext;
use Thelia\Domain\Pricing\CustomerPrice\ResolvedPrice;
use Thelia\Domain\Pricing\EffectivePrice;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\Pricing\Rule\Storage\BasePriceLoader;
use Thelia\Domain\Pricing\Rule\Storage\BasePrices;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Tests\Support\Pricing\LoadedCustomerDiscountRate;

/**
 * One branch of the price of a customer per test: the price of the group, the
 * promotions, the discount of the group, the rules and operations competing with
 * them, the sale unit, and the discount of the customer last.
 */
final class CustomerPriceContextTest extends TestCase
{
    private const PSE = 12;

    private const CURRENCY = 1;

    private Currency $currency;

    protected function setUp(): void
    {
        $this->currency = new Currency();
        $this->currency->setId(self::CURRENCY);
    }

    public function testWithoutAPriceOfItsOwnTheGroupPaysTheCatalogPriceLessItsDiscount(): void
    {
        $price = $this->resolveOne($this->context(base: $this->base(100.0)), $this->customer(groupDiscount: 15.0));

        self::assertEqualsWithDelta(85.0, $price->untaxedPrice, 1e-9);
        self::assertEqualsWithDelta(85.0, $price->untaxedPromoPrice, 1e-9);
        self::assertFalse($price->isPromo);
        self::assertSame(ResolvedPrice::SOURCE_BASE, $price->source);
        self::assertSame(15.0, $price->customerGroupDiscountRate);
    }

    /**
     * The ticket: only the base price gets the discount of the group. Base 100, promo 80,
     * group 15 % → 85 shown against 80.
     */
    public function testTheDiscountOfTheGroupDoesNotReachTheCatalogPromotion(): void
    {
        $price = $this->resolveOne($this->context(base: $this->base(100.0, promoPrice: 80.0, isPromo: true)), $this->customer(groupDiscount: 15.0));

        self::assertEqualsWithDelta(85.0, $price->untaxedPrice, 1e-9);
        self::assertEqualsWithDelta(80.0, $price->untaxedPromoPrice, 1e-9);
        self::assertTrue($price->isPromo);
        self::assertTrue($price->displayInitialPrice);
        self::assertSame(ResolvedPrice::SOURCE_BASE, $price->source);
    }

    /**
     * Base 100, promo 80, group 15 %, customer 10 %: 100 x 0.85 x 0.9 = 76.5, and the
     * promotion 80 x 0.9 = 72 (the customer discount alone reaches it).
     */
    public function testTheCatalogPromotionOnlyGetsTheDiscountOfTheCustomer(): void
    {
        $price = $this->resolveOne(
            $this->context(base: $this->base(100.0, promoPrice: 80.0, isPromo: true)),
            $this->customer(groupDiscount: 15.0, customerDiscount: 10.0),
        );

        self::assertEqualsWithDelta(76.5, $price->untaxedPrice, 1e-9);
        self::assertEqualsWithDelta(72.0, $price->untaxedPromoPrice, 1e-9);
        self::assertTrue($price->isPromo);
        self::assertEqualsWithDelta(72.0, $price->chargedPrice(), 1e-9);
    }

    /**
     * The cheaper wins: base 100, promo 90, group 15 % → the discounted base (85) is the
     * price, and the line is no promotion.
     */
    public function testTheDiscountedBaseWinsOverACatalogPromotionThatIsDearer(): void
    {
        $price = $this->resolveOne($this->context(base: $this->base(100.0, promoPrice: 90.0, isPromo: true)), $this->customer(groupDiscount: 15.0));

        self::assertEqualsWithDelta(85.0, $price->untaxedPrice, 1e-9);
        self::assertEqualsWithDelta(85.0, $price->untaxedPromoPrice, 1e-9);
        self::assertFalse($price->isPromo);
        self::assertFalse($price->displayInitialPrice);
        self::assertEqualsWithDelta(85.0, $price->chargedPrice(), 1e-9);
    }

    public function testTheDiscountOfTheCustomerAppliesOnTheDiscountedBaseThatWonOverTheCatalogPromotion(): void
    {
        $price = $this->resolveOne(
            $this->context(base: $this->base(100.0, promoPrice: 90.0, isPromo: true)),
            $this->customer(groupDiscount: 15.0, customerDiscount: 10.0),
        );

        self::assertEqualsWithDelta(76.5, $price->untaxedPrice, 1e-9);
        self::assertEqualsWithDelta(76.5, $price->chargedPrice(), 1e-9);
        self::assertFalse($price->isPromo);
    }

    public function testAGroupPriceStaysUntouchedByTheDiscountOfTheGroupAndNotByTheCustomersOnTopOfACatalogPromotion(): void
    {
        $price = $this->resolveOne(
            $this->context(base: $this->base(100.0, promoPrice: 80.0, isPromo: true), groupPrice: new CustomerGroupPrice(self::PSE, 42.0, null)),
            $this->customer(groupDiscount: 15.0, customerDiscount: 10.0),
        );

        self::assertEqualsWithDelta(37.8, $price->untaxedPrice, 1e-9);
        self::assertEqualsWithDelta(37.8, $price->chargedPrice(), 1e-9);
        self::assertFalse($price->isPromo);
        self::assertSame(ResolvedPrice::SOURCE_CUSTOMER_GROUP, $price->source);
    }

    public function testAPriceOfTheGroupReplacesTheCatalogPriceAndItsPromotion(): void
    {
        $price = $this->resolveOne(
            $this->context(base: $this->base(100.0, promoPrice: 30.0, isPromo: true), groupPrice: new CustomerGroupPrice(self::PSE, 42.0, null)),
            $this->customer(groupDiscount: 15.0),
        );

        self::assertEqualsWithDelta(42.0, $price->untaxedPrice, 1e-9);
        self::assertEqualsWithDelta(42.0, $price->untaxedPromoPrice, 1e-9);
        self::assertFalse($price->isPromo, 'a group price without a promo price of its own is no promotion');
        self::assertSame(ResolvedPrice::SOURCE_CUSTOMER_GROUP, $price->source);
    }

    public function testAPromoPriceOfTheGroupIsAPromotion(): void
    {
        $price = $this->resolveOne(
            $this->context(base: $this->base(100.0), groupPrice: new CustomerGroupPrice(self::PSE, 42.0, 35.0)),
            $this->customer(),
        );

        self::assertEqualsWithDelta(42.0, $price->untaxedPrice, 1e-9);
        self::assertEqualsWithDelta(35.0, $price->untaxedPromoPrice, 1e-9);
        self::assertTrue($price->isPromo);
    }

    public function testAPromoPriceOfTheGroupAloneIsShownAgainstTheDiscountedCatalogPrice(): void
    {
        $price = $this->resolveOne(
            $this->context(base: $this->base(100.0), groupPrice: new CustomerGroupPrice(self::PSE, null, 35.0)),
            $this->customer(groupDiscount: 10.0),
        );

        self::assertEqualsWithDelta(90.0, $price->untaxedPrice, 1e-9);
        self::assertEqualsWithDelta(35.0, $price->untaxedPromoPrice, 1e-9);
        self::assertTrue($price->isPromo);
        self::assertSame(ResolvedPrice::SOURCE_CUSTOMER_GROUP, $price->source);
    }

    /**
     * The example of the plan: base 100, group 15 %, customer 10 % → 76.5; with a
     * group price of 42 → 37.8.
     */
    public function testTheDiscountOfTheCustomerAppliesLastOnTopOfTheGroup(): void
    {
        $customer = $this->customer(groupDiscount: 15.0, customerDiscount: 10.0);

        $fromBase = $this->resolveOne($this->context(base: $this->base(100.0)), $customer);
        $fromGroupPrice = $this->resolveOne($this->context(base: $this->base(100.0), groupPrice: new CustomerGroupPrice(self::PSE, 42.0, null)), $customer);

        self::assertEqualsWithDelta(76.5, $fromBase->untaxedPrice, 1e-9);
        self::assertSame(ResolvedPrice::SOURCE_BASE, $fromBase->source);
        self::assertSame(10.0, $fromBase->customerDiscountRate);
        self::assertEqualsWithDelta(37.8, $fromGroupPrice->untaxedPrice, 1e-9);
        self::assertSame(ResolvedPrice::SOURCE_CUSTOMER_GROUP, $fromGroupPrice->source);
    }

    /**
     * As every reader before the context: a discount of the customer that is not
     * positive changes nothing, it never raises the price.
     */
    public function testANegativeDiscountOfTheCustomerNeverRaisesThePrice(): void
    {
        $price = $this->resolveOne($this->context(base: $this->base(100.0)), $this->customer(customerDiscount: -10.0));

        self::assertEqualsWithDelta(100.0, $price->untaxedPrice, 1e-9);
        self::assertSame(0.0, $price->customerDiscountRate);
    }

    /**
     * The rule of the group: a discount outside ]0, 100] counts for nothing, it neither
     * charges a negative price nor gives the goods away.
     */
    public function testADiscountOfTheCustomerAboveAHundredCountsForNothing(): void
    {
        $price = $this->resolveOne($this->context(base: $this->base(100.0)), $this->customer(customerDiscount: 150.0));

        self::assertEqualsWithDelta(100.0, $price->untaxedPrice, 1e-9);
        self::assertSame(0.0, $price->customerDiscountRate);
    }

    public function testADiscountOfExactlyAHundredIsTheOnlyOneThatGivesTheGoodsAway(): void
    {
        $price = $this->resolveOne($this->context(base: $this->base(100.0)), $this->customer(customerDiscount: 100.0));

        self::assertEqualsWithDelta(0.0, $price->untaxedPrice, 1e-9);
        self::assertSame(100.0, $price->customerDiscountRate);
    }

    public function testTheChargedPriceIsThePromoPriceOnlyDuringAPromotion(): void
    {
        $onPromotion = $this->resolveOne($this->context(base: $this->base(100.0, 70.0, isPromo: true)), $this->customer());
        $offPromotion = $this->resolveOne($this->context(base: $this->base(100.0, 70.0)), $this->customer());

        self::assertEqualsWithDelta(70.0, $onPromotion->chargedPrice(), 1e-9);
        self::assertEqualsWithDelta(100.0, $offPromotion->chargedPrice(), 1e-9);
    }

    public function testANegativeDiscountOfTheGroupIsIgnored(): void
    {
        $price = $this->resolveOne($this->context(base: $this->base(100.0)), $this->customer(groupDiscount: -10.0));

        self::assertEqualsWithDelta(100.0, $price->untaxedPrice, 1e-9);
        self::assertSame(0.0, $price->customerGroupDiscountRate);
    }

    /**
     * A rate above 100 written straight into the database would make the price
     * negative: it is ignored rather than capped, so a typo never gives goods away.
     */
    public function testADiscountOfTheGroupAboveAHundredIsIgnored(): void
    {
        $price = $this->resolveOne($this->context(base: $this->base(100.0)), $this->customer(groupDiscount: 150.0));

        self::assertEqualsWithDelta(100.0, $price->untaxedPrice, 1e-9);
        self::assertSame(0.0, $price->customerGroupDiscountRate);
    }

    public function testADiscountOfTheGroupOfAHundredIsKept(): void
    {
        $price = $this->resolveOne($this->context(base: $this->base(100.0)), $this->customer(groupDiscount: 100.0));

        self::assertEqualsWithDelta(0.0, $price->untaxedPrice, 1e-9);
    }

    public function testAPriceInTheCurrencyAskedIsReadThere(): void
    {
        $currency = new Currency();
        $currency->setId(2);
        $context = $this->context(base: new BasePrices(self::PSE, 1, [self::CURRENCY => 100.0, 2 => 150.0], [self::CURRENCY => 100.0, 2 => 150.0]));

        $price = $context->resolve([self::PSE], $currency, $this->customer(groupDiscount: 10.0))[self::PSE];

        self::assertEqualsWithDelta(135.0, $price->untaxedPrice, 1e-9);
    }

    public function testASaleElementWithoutAnyPriceIsAbsent(): void
    {
        $context = $this->context(base: new BasePrices(self::PSE, 1, []));

        self::assertSame([], $context->resolve([self::PSE, 99], $this->currency, $this->customer(groupDiscount: 10.0)));
    }

    public function testARuleCheaperThanThePriceOfTheGroupWins(): void
    {
        $price = $this->resolveOne(
            $this->context(
                base: $this->base(100.0),
                groupPrice: new CustomerGroupPrice(self::PSE, 60.0, null),
                effective: new EffectivePrice(self::PSE, 50.0, true, EffectivePrice::SOURCE_RULE, 3),
            ),
            $this->customer(customerDiscount: 10.0),
        );

        self::assertEqualsWithDelta(54.0, $price->untaxedPrice, 1e-9, 'the promotion is shown against the price of the group');
        self::assertEqualsWithDelta(45.0, $price->untaxedPromoPrice, 1e-9);
        self::assertTrue($price->isPromo);
        self::assertTrue($price->displayInitialPrice);
        self::assertSame(EffectivePrice::SOURCE_RULE, $price->source);
    }

    public function testAPriceOfTheGroupCheaperThanTheRuleWins(): void
    {
        $price = $this->resolveOne(
            $this->context(
                base: $this->base(100.0),
                groupPrice: new CustomerGroupPrice(self::PSE, 40.0, null),
                effective: new EffectivePrice(self::PSE, 50.0, true, EffectivePrice::SOURCE_RULE, 3),
            ),
            $this->customer(),
        );

        self::assertEqualsWithDelta(40.0, $price->untaxedPromoPrice, 1e-9);
        self::assertFalse($price->isPromo);
        self::assertSame(ResolvedPrice::SOURCE_CUSTOMER_GROUP, $price->source);
    }

    public function testAReservedOperationCompetesWithTheDiscountOfTheGroup(): void
    {
        $reserved = new EffectivePrice(self::PSE, 82.0, false, EffectivePrice::SOURCE_RESERVED_SALE, saleId: 4);

        $groupWins = $this->resolveOne($this->context(base: $this->base(100.0), effective: $reserved), $this->customer(groupDiscount: 20.0));
        $operationWins = $this->resolveOne($this->context(base: $this->base(100.0), effective: $reserved), $this->customer(groupDiscount: 10.0));

        self::assertEqualsWithDelta(80.0, $groupWins->untaxedPromoPrice, 1e-9);
        self::assertSame(ResolvedPrice::SOURCE_BASE, $groupWins->source);
        self::assertEqualsWithDelta(82.0, $operationWins->untaxedPromoPrice, 1e-9);
        self::assertEqualsWithDelta(90.0, $operationWins->untaxedPrice, 1e-9);
        self::assertSame(EffectivePrice::SOURCE_RESERVED_SALE, $operationWins->source);
    }

    /**
     * A group that changes nothing keeps the historical behaviour: the rule replaces
     * the catalog promotion, even a cheaper one.
     */
    public function testForAGroupThatChangesNothingTheRuleReplacesTheCatalogPromotion(): void
    {
        $price = $this->resolveOne(
            $this->context(
                base: $this->base(100.0, promoPrice: 70.0, isPromo: true),
                effective: new EffectivePrice(self::PSE, 75.0, false, EffectivePrice::SOURCE_RULE, 3),
            ),
            $this->customer(),
        );

        self::assertEqualsWithDelta(75.0, $price->untaxedPromoPrice, 1e-9);
        self::assertSame(EffectivePrice::SOURCE_RULE, $price->source);
    }

    public function testTheSaleUnitPriceIsThePriceLessTheDiscountOfTheGroupWhateverTheGroupPrice(): void
    {
        $price = $this->resolveOne(
            $this->context(
                base: $this->base(100.0),
                groupPrice: new CustomerGroupPrice(self::PSE, 40.0, null),
                effective: new EffectivePrice(self::PSE, 90.0, false, EffectivePrice::SOURCE_SALE_UNIT, saleUnitId: 2),
            ),
            $this->customer(groupDiscount: 10.0, customerDiscount: 10.0),
        );

        self::assertEqualsWithDelta(72.9, $price->untaxedPrice, 1e-9);
        self::assertEqualsWithDelta(72.9, $price->untaxedPromoPrice, 1e-9);
        self::assertFalse($price->isPromo);
        self::assertSame(EffectivePrice::SOURCE_SALE_UNIT, $price->source);
    }

    public function testASecondAskForTheSameSaleElementsReadsNothingAgain(): void
    {
        $basePriceLoader = $this->createMock(BasePriceLoader::class);
        $basePriceLoader->expects(self::once())->method('load')->willReturn([self::PSE => $this->base(100.0)]);
        $context = new CustomerPriceContext(
            $basePriceLoader,
            $this->createStub(CustomerGroupPriceLoader::class),
            $this->createStub(EffectivePriceCatalog::class),
            $this->probe(),
            $this->groupResolver(),
            new LoadedCustomerDiscountRate(),
        );
        $customer = $this->customer();

        $first = $context->resolve([self::PSE], $this->currency, $customer);
        $second = $context->resolve([self::PSE, self::PSE], $this->currency, $customer);

        self::assertSame($first[self::PSE], $second[self::PSE]);
    }

    public function testTheGroupSaysWhetherPricesAreShownTaxed(): void
    {
        self::assertTrue($this->context(base: $this->base(100.0))->displaysTaxedPrices($this->customer()));
        self::assertFalse($this->context(base: $this->base(100.0))->displaysTaxedPrices($this->customer(displayTaxedPrice: false)));
    }

    private function resolveOne(CustomerPriceContext $context, Customer $customer): ResolvedPrice
    {
        $prices = $context->resolve([self::PSE], $this->currency, $customer);
        self::assertArrayHasKey(self::PSE, $prices);

        return $prices[self::PSE];
    }

    private function context(BasePrices $base, ?CustomerGroupPrice $groupPrice = null, ?EffectivePrice $effective = null): CustomerPriceContext
    {
        $basePriceLoader = $this->createStub(BasePriceLoader::class);
        $basePriceLoader->method('load')->willReturn([self::PSE => $base]);
        $groupPriceLoader = $this->createStub(CustomerGroupPriceLoader::class);
        $groupPriceLoader->method('load')->willReturn(null === $groupPrice ? [] : [self::PSE => $groupPrice]);
        $catalog = $this->createStub(EffectivePriceCatalog::class);
        $catalog->method('warm')->willReturn(null === $effective ? [] : [self::PSE => $effective]);

        return new CustomerPriceContext($basePriceLoader, $groupPriceLoader, $catalog, $this->probe(), $this->groupResolver(), new LoadedCustomerDiscountRate());
    }

    /**
     * A shop where the groups price: with prices of their own and discounts.
     */
    private function probe(): CatalogActivityProbe
    {
        $probe = $this->createStub(CatalogActivityProbe::class);
        $probe->method('read')->willReturn(new CatalogActivity(false, false, false, true, true, false, 1, true, true));

        return $probe;
    }

    /**
     * The group of a customer is the one the test customer carries in memory.
     */
    private function groupResolver(): CustomerGroupResolver
    {
        $resolver = $this->createStub(CustomerGroupResolver::class);
        $resolver->method('groupOf')->willReturnCallback(static fn (?Customer $customer): CustomerGroup => $customer?->getCustomerGroup() ?? new CustomerGroup());
        $resolver->method('defaultGroupId')->willReturn(1);

        return $resolver;
    }

    private function base(float $price, ?float $promoPrice = null, bool $isPromo = false): BasePrices
    {
        return new BasePrices(self::PSE, 1, [self::CURRENCY => $price], [self::CURRENCY => $promoPrice ?? $price], $isPromo);
    }

    private function customer(float $groupDiscount = 0.0, float $customerDiscount = 0.0, bool $displayTaxedPrice = true): Customer
    {
        $group = new CustomerGroup();
        $group->setId(5);
        $group->setDiscount((string) $groupDiscount);
        $group->setDisplayTaxedPrice($displayTaxedPrice ? 1 : 0);

        // The relation setter registers the customer on the group, which needs the
        // table maps: the getter is answered in memory instead.
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
        $customer->setDiscount((string) $customerDiscount);

        return $customer;
    }
}
