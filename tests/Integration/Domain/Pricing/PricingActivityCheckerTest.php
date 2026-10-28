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

namespace Thelia\Tests\Integration\Domain\Pricing;

use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\CustomerPrice\CustomerDiscountRate;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\SaleUnit\Pricing\SaleUnitPricingActivityChecker;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleQuery;
use Thelia\Model\Currency;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\Sale;
use Thelia\Model\SaleQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * Whether any rule, operation or customer group prices the catalog, asked of the
 * database in one statement per request.
 */
final class PricingActivityCheckerTest extends ActionIntegrationTestCase
{
    use RecordsSqlQueries;

    private CatalogActivityProbe $probe;

    protected function setUp(): void
    {
        parent::setUp();

        // Whatever rule or reserved operation the test database holds is turned off, and
        // every group discount cleared, so each test starts from a shop without any.
        CatalogPriceRuleQuery::create()->update(['Active' => false]);
        SaleQuery::create()->update(['Active' => false]);
        CustomerGroupQuery::create()->update(['Discount' => '0']);
    }

    public function testAPublicRuleTurnedOnLaterInTheRequestIsSeenAfterTheReset(): void
    {
        $checker = $this->checker();

        self::assertFalse($checker->hasActivePublicRule());

        $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0]);

        self::assertFalse($checker->hasActivePublicRule(), 'the answer is kept for the request');
        self::assertFalse($checker->hasActiveNamedRule());

        $this->probe->reset();

        self::assertTrue($checker->hasActivePublicRule());
    }

    public function testAReservedRuleTurnedOnLaterInTheRequestIsSeenAfterTheReset(): void
    {
        $checker = $this->checker();

        self::assertFalse($checker->hasActiveNamedRule());

        $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS]);

        self::assertFalse($checker->hasActiveNamedRule(), 'the answer is kept for the request');
        self::assertFalse($checker->hasActivePublicRule());

        $this->probe->reset();

        self::assertTrue($checker->hasActiveNamedRule());
    }

    public function testPricingDoesNotDependOnTheVisitorWhileNothingReservedRuns(): void
    {
        $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0]);
        $this->factory->catalogPriceRule(['active' => false, 'percentageValue' => 10.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS]);

        self::assertFalse($this->checker()->hasVisitorDependentPricing());
    }

    public function testATurnedOnReservedRuleMakesPricingDependOnTheVisitor(): void
    {
        $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS]);

        self::assertTrue($this->checker()->hasVisitorDependentPricing());
    }

    public function testARunningReservedSaleMakesPricingDependOnTheVisitor(): void
    {
        $this->factory->sale(['active' => true, 'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS]);

        self::assertTrue($this->checker()->hasVisitorDependentPricing());
    }

    public function testEveryQuestionOfARequestIsOneStatement(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $checker = $this->checker();

        $statements = $this->recordSqlQueries(static function () use ($checker, $customer): void {
            $checker->hasActivePublicRule();
            $checker->hasActiveNamedRule();
            $checker->hasCustomerGroupPrices();
            $checker->hasDiscountedCustomerGroup();
            $checker->hasVisitorDependentPricing();
            $checker->mayPriceBeyondCatalog(null);
            $checker->mayPriceBeyondCatalog($customer);
            $checker->mayResolveEffectivePriceFor($customer);
            $checker->pricesDependOnWhoIsAsking($customer);
        });

        $ofTheShop = array_filter($statements, static fn (string $sql): bool => str_contains($sql, 'catalog_price_rule'));
        $ofTheCustomer = array_filter($statements, static fn (string $sql): bool => str_contains($sql, 'FROM `customer`'));

        self::assertCount(1, $ofTheShop, implode("\n", $statements));
        self::assertCount(1, $ofTheCustomer, 'The discount of the customer is read once for the request, however many questions ask.');
        self::assertCount(2, $statements, implode("\n", $statements));
    }

    public function testAVisitorIsNotPricedByWhatIsReservedForCustomers(): void
    {
        $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS]);
        $this->factory->sale(['active' => true, 'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS]);
        $checker = $this->checker();

        self::assertFalse($checker->mayPriceBeyondCatalog(null));
        self::assertTrue($checker->mayPriceBeyondCatalog($this->factory->customer($this->factory->customerTitle())));
    }

    public function testNothingPricesBeyondTheCatalogOfAShopWithoutRuleOperationGroupOrDiscount(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $checker = $this->checker();

        self::assertFalse($checker->mayPriceBeyondCatalog(null));
        self::assertFalse($checker->mayPriceBeyondCatalog($customer));
        self::assertFalse($checker->pricesDependOnWhoIsAsking($customer));
    }

    public function testAGroupDiscountPricesEveryVisitorBeyondTheCatalog(): void
    {
        $this->factory->customerGroup(discount: 5.0);

        self::assertTrue($this->checker()->mayPriceBeyondCatalog(null));
    }

    public function testADiscountOfTheCustomerPricesThemBeyondTheCatalog(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $customer->setDiscount('5')->save();

        self::assertTrue($this->checker()->mayPriceBeyondCatalog($customer));
        self::assertTrue($this->checker()->pricesDependOnWhoIsAsking($customer));
    }

    public function testAGroupDiscountOutsideTheRangeDiscountsNothing(): void
    {
        $this->factory->customerGroup(discount: 150.0);
        $this->factory->customerGroup(discount: -5.0);

        self::assertFalse($this->checker()->hasDiscountedCustomerGroup());
    }

    public function testAGroupWithADiscountMakesPricingDependOnTheVisitor(): void
    {
        $checker = $this->checker();
        self::assertFalse($checker->hasVisitorDependentPricing());

        $this->factory->customerGroup(discount: 5.0);

        self::assertFalse($checker->hasVisitorDependentPricing(), 'the answer is kept for the request');
        $this->probe->reset();
        self::assertTrue($checker->hasVisitorDependentPricing());
    }

    public function testAPriceOfAGroupMakesPricingDependOnTheVisitor(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency);
        $this->factory->customerGroupProductPrice($this->factory->customerGroup(), $product->getDefaultSaleElements(), $currency, promoPrice: 8.0);

        self::assertTrue($this->checker()->hasCustomerGroupPrices());
        self::assertTrue($this->checker()->hasVisitorDependentPricing());
    }

    public function testARowOfAGroupWithoutAnyPriceIsNoPrice(): void
    {
        $currency = $this->factory->currency();
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency);
        $this->factory->customerGroupProductPrice($this->factory->customerGroup(), $product->getDefaultSaleElements(), $currency);

        self::assertFalse($this->checker()->hasCustomerGroupPrices());
        self::assertFalse($this->checker()->hasVisitorDependentPricing());
    }

    public function testATurnedOnRuleForCustomerGroupsMakesPricingDependOnTheVisitor(): void
    {
        $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMER_GROUPS]);

        self::assertTrue($this->checker()->hasVisitorDependentPricing());
    }

    public function testOnlyAPositiveDiscountMakesADiscountedCustomerAndItIsReadOncePerCustomer(): void
    {
        $discounted = $this->factory->customer($this->factory->customerTitle());
        $discounted->setDiscount('5')->save();
        $negative = $this->factory->customer($this->factory->customerTitle());
        $negative->setDiscount('-5')->save();
        $aboveAHundred = $this->factory->customer($this->factory->customerTitle());
        $aboveAHundred->setDiscount('150')->save();
        $plain = $this->factory->customer($this->factory->customerTitle());
        $checker = $this->checker();

        $statements = $this->recordSqlQueries(static function () use ($checker, $discounted, $negative, $aboveAHundred, $plain): void {
            self::assertTrue($checker->hasDiscountedCustomer($discounted));
            self::assertFalse($checker->hasDiscountedCustomer($negative));
            self::assertFalse($checker->hasDiscountedCustomer($aboveAHundred), 'As the discount of a group, one above a hundred counts for nothing.');
            self::assertFalse($checker->hasDiscountedCustomer($plain));
            self::assertFalse($checker->hasDiscountedCustomer(null));
        });

        self::assertCount(4, $statements, 'One read per customer stored, none for a visitor.');

        $again = $this->recordSqlQueries(static function () use ($checker, $discounted, $plain): void {
            $checker->hasDiscountedCustomer($discounted);
            $checker->hasDiscountedCustomer($plain);
        });

        self::assertSame([], $again, 'Kept for the request.');
    }

    public function testARuleOpenToEveryoneDoesNotMakeThePriceDifferFromThePublicOne(): void
    {
        $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0]);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $checker = $this->checker();

        self::assertTrue($checker->mayPriceBeyondCatalog(null), 'It prices beyond the catalog columns...');
        self::assertFalse($checker->mayOrderDifferFromThePublicPrice(null), '...but it is the public price: every visitor owes it.');
        self::assertFalse($checker->mayOrderDifferFromThePublicPrice($customer));
    }

    public function testWhatIsReservedForNamedCustomersMakesThePriceDifferForThemOnly(): void
    {
        $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS]);
        $this->factory->sale(['active' => true, 'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS]);
        $checker = $this->checker();

        self::assertFalse($checker->mayOrderDifferFromThePublicPrice(null));
        self::assertTrue($checker->mayOrderDifferFromThePublicPrice($this->factory->customer($this->factory->customerTitle())));
    }

    public function testAGroupPricingOrDiscountingForItsMembersMovesTheOrderOfThemOnly(): void
    {
        $group = $this->factory->customerGroup(discount: 5.0);
        $this->factory->customerGroupProductPrice($group, $this->factory->product($this->factory->category(), $this->factory->taxRule(), Currency::getDefaultCurrency())->getDefaultSaleElements(), Currency::getDefaultCurrency(), 10.0);
        $member = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
        $ofTheDefaultGroup = $this->factory->customer($this->factory->customerTitle());
        $checker = $this->checker();

        self::assertTrue($checker->mayPriceBeyondCatalog(null), 'A group prices something: the cards resolve...');
        self::assertFalse($checker->mayOrderDifferFromThePublicPrice(null), '...but the visitor is of the default group, which prices nothing.');
        self::assertFalse($checker->mayOrderDifferFromThePublicPrice($ofTheDefaultGroup));
        self::assertTrue($checker->mayOrderDifferFromThePublicPrice($member));
    }

    public function testAGroupPriceAloneMovesTheOrderOfItsMembers(): void
    {
        $group = $this->factory->customerGroup();
        $this->factory->customerGroupProductPrice($group, $this->factory->product($this->factory->category(), $this->factory->taxRule(), Currency::getDefaultCurrency())->getDefaultSaleElements(), Currency::getDefaultCurrency(), null, 7.0);
        $checker = $this->checker();

        self::assertTrue($checker->mayOrderDifferFromThePublicPrice($this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group])));
        self::assertFalse($checker->mayOrderDifferFromThePublicPrice(null));
    }

    public function testADiscountOfTheDefaultGroupMovesTheOrderOfTheVisitor(): void
    {
        $this->factory->customerGroup(discount: 5.0, isDefault: true);
        $other = $this->factory->customerGroup();
        $checker = $this->checker();

        self::assertTrue($checker->mayOrderDifferFromThePublicPrice(null), 'A visitor with no account is of the default group.');
        self::assertFalse($checker->mayOrderDifferFromThePublicPrice($this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $other])));
    }

    public function testADiscountOfTheCustomerAloneDoesNotMoveTheOrder(): void
    {
        $discounted = $this->factory->customer($this->factory->customerTitle());
        $discounted->setDiscount('5')->save();
        $plain = $this->factory->customer($this->factory->customerTitle());
        $checker = $this->checker();

        self::assertTrue($checker->pricesDependOnWhoIsAsking($discounted));
        self::assertFalse($checker->mayOrderDifferFromThePublicPrice($discounted), 'A rate that applies to every price alike ranks the products as before.');
        self::assertFalse($checker->mayOrderDifferFromThePublicPrice($plain));
    }

    public function testASaleUnitPricingSomethingMakesThePriceDiffer(): void
    {
        $saleUnitChecker = $this->createStub(SaleUnitPricingActivityChecker::class);
        $saleUnitChecker->method('currentSaleUnitPricesAnything')->willReturn(true);
        $this->probe = new CatalogActivityProbe();

        self::assertTrue((new PricingActivityChecker($this->probe, new CustomerDiscountRate(), new CustomerGroupResolver($this->probe), $saleUnitChecker))->mayOrderDifferFromThePublicPrice(null));
        self::assertFalse($this->checker()->mayOrderDifferFromThePublicPrice(null));
    }

    private function checker(): PricingActivityChecker
    {
        $this->probe = new CatalogActivityProbe();

        return new PricingActivityChecker($this->probe, new CustomerDiscountRate(), new CustomerGroupResolver($this->probe));
    }
}
