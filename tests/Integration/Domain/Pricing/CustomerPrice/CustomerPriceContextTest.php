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

namespace Thelia\Tests\Integration\Domain\Pricing\CustomerPrice;

use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\CustomerPrice\CustomerDiscountRate;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContext;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContextInterface;
use Thelia\Domain\Pricing\CustomerPrice\ResolvedPrice;
use Thelia\Domain\Pricing\EffectivePrice;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\Pricing\Rule\Audience\CustomerAudience;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Domain\SaleUnit\Pricing\SaleUnitPricingActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleCustomerGroup;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\Sale;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The price of a customer against the whole engine: the price of the group facing
 * the rules and the reserved operations, the sale unit first, and the cost of a
 * batch.
 */
final class CustomerPriceContextTest extends ActionIntegrationTestCase
{
    use RecordsSqlQueries;

    private CustomerPriceContextInterface $context;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetRequestMemos();
        $this->context = $this->getService(CustomerPriceContextInterface::class);
        $this->currency = Currency::getDefaultCurrency();
    }

    protected function tearDown(): void
    {
        $this->resetRequestMemos();

        parent::tearDown();
    }

    public function testTheServiceIsTheContextOfTheCore(): void
    {
        self::assertInstanceOf(CustomerPriceContext::class, $this->context);
    }

    public function testAVisitorAndAGuestAccountBelongToTheDefaultGroup(): void
    {
        $guest = $this->member($this->factory->customerGroup(discount: 30.0));
        $guest->setIsGuest(1)->save();
        $pse = $this->saleElement();

        self::assertSame(1, (int) $this->context->customerGroupOf(null)->getIsDefault());
        self::assertSame(1, (int) $this->context->customerGroupOf($guest)->getIsDefault());
        self::assertEqualsWithDelta(100.0, $this->context->resolve([$pse->getId()], $this->currency, null)[$pse->getId()]->untaxedPrice, 0.000001);
        self::assertEqualsWithDelta(100.0, $this->context->resolve([$pse->getId()], $this->currency, $guest)[$pse->getId()]->untaxedPrice, 0.000001);
    }

    public function testAMemberGetsTheGroupDiscountTheTaxDisplayAndTheirOwnDiscount(): void
    {
        $member = $this->member($this->factory->customerGroup(discount: 15.0, displayTaxedPrice: false));
        $member->setDiscount('10')->save();
        $pse = $this->saleElement();

        $price = $this->context->resolve([$pse->getId()], $this->currency, $member)[$pse->getId()];

        self::assertEqualsWithDelta(76.5, $price->untaxedPrice, 0.000001);
        self::assertSame(ResolvedPrice::SOURCE_BASE, $price->source);
        self::assertSame((int) $member->getCustomerGroupId(), $price->customerGroupId);
        self::assertFalse($this->context->displaysTaxedPrices($member));
        self::assertTrue($this->context->displaysTaxedPrices(null));
    }

    public function testThePriceOfTheGroupAndAPublicRuleCompeteAndTheLowerWins(): void
    {
        $product = $this->product();
        $pse = $product->getDefaultSaleElements();
        $this->publicRule($product, 20.0);
        $cheaperGroup = $this->factory->customerGroup();
        $dearerGroup = $this->factory->customerGroup();
        $this->factory->customerGroupProductPrice($cheaperGroup, $pse, $this->currency, 70.0);
        $this->factory->customerGroupProductPrice($dearerGroup, $pse, $this->currency, 90.0);

        $groupWins = $this->context->resolve([$pse->getId()], $this->currency, $this->member($cheaperGroup))[$pse->getId()];
        $ruleWins = $this->context->resolve([$pse->getId()], $this->currency, $this->member($dearerGroup))[$pse->getId()];
        $visitor = $this->context->resolve([$pse->getId()], $this->currency, null)[$pse->getId()];

        self::assertEqualsWithDelta(70.0, $groupWins->untaxedPromoPrice, 0.000001);
        self::assertSame(ResolvedPrice::SOURCE_CUSTOMER_GROUP, $groupWins->source);
        self::assertEqualsWithDelta(80.0, $ruleWins->untaxedPromoPrice, 0.000001);
        self::assertEqualsWithDelta(90.0, $ruleWins->untaxedPrice, 0.000001);
        self::assertSame(EffectivePrice::SOURCE_RULE, $ruleWins->source);
        self::assertEqualsWithDelta(80.0, $visitor->untaxedPromoPrice, 0.000001);
        self::assertSame(EffectivePrice::SOURCE_RULE, $visitor->source);
    }

    public function testThePriceOfTheGroupAndAReservedOperationCompeteAndTheLowerWins(): void
    {
        $product = $this->product();
        $pse = $product->getDefaultSaleElements();
        $cheaperGroup = $this->factory->customerGroup();
        $dearerGroup = $this->factory->customerGroup();
        $this->factory->customerGroupProductPrice($cheaperGroup, $pse, $this->currency, 85.0);
        $this->factory->customerGroupProductPrice($dearerGroup, $pse, $this->currency, 95.0);
        $groupWinner = $this->member($cheaperGroup);
        $operationWinner = $this->member($dearerGroup);
        $sale = $this->reservedSale($product, 10.0);
        $this->factory->saleCustomer($sale, $groupWinner);
        $this->factory->saleCustomer($sale, $operationWinner);

        $groupWins = $this->context->resolve([$pse->getId()], $this->currency, $groupWinner)[$pse->getId()];
        $operationWins = $this->context->resolve([$pse->getId()], $this->currency, $operationWinner)[$pse->getId()];

        self::assertEqualsWithDelta(85.0, $groupWins->untaxedPromoPrice, 0.000001);
        self::assertSame(ResolvedPrice::SOURCE_CUSTOMER_GROUP, $groupWins->source);
        self::assertEqualsWithDelta(90.0, $operationWins->untaxedPromoPrice, 0.000001);
        self::assertSame(EffectivePrice::SOURCE_RESERVED_SALE, $operationWins->source);
    }

    public function testARuleReservedForTheGroupPricesItsMembers(): void
    {
        $product = $this->product();
        $pse = $product->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $rule = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 25.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMER_GROUPS]);
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        (new CatalogPriceRuleCustomerGroup())->setCatalogPriceRuleId($rule->getId())->setCustomerGroupId($group->getId())->save();
        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);

        $member = $this->context->resolve([$pse->getId()], $this->currency, $this->member($group))[$pse->getId()];
        $other = $this->context->resolve([$pse->getId()], $this->currency, $this->member($this->factory->customerGroup()))[$pse->getId()];

        self::assertEqualsWithDelta(75.0, $member->untaxedPromoPrice, 0.000001);
        self::assertSame(EffectivePrice::SOURCE_RULE, $member->source);
        self::assertSame(ResolvedPrice::SOURCE_BASE, $other->source);
        self::assertFalse($other->isPromo);
    }

    public function testTheSaleUnitPriceComesBeforeThePriceOfTheGroup(): void
    {
        $product = $this->product();
        $pse = $product->getDefaultSaleElements();
        $group = $this->factory->customerGroup(discount: 10.0);
        $this->factory->customerGroupProductPrice($group, $pse, $this->currency, 40.0);
        $saleUnit = $this->factory->saleUnit();
        $this->factory->saleUnitProductPrice($saleUnit, $pse, $this->currency, 80.0);
        $this->getService(SaleUnitContext::class)->setSaleUnit($saleUnit);

        $price = $this->context->resolve([$pse->getId()], $this->currency, $this->member($group))[$pse->getId()];

        self::assertEqualsWithDelta(72.0, $price->untaxedPrice, 0.000001);
        self::assertFalse($price->isPromo);
        self::assertSame(EffectivePrice::SOURCE_SALE_UNIT, $price->source);
    }

    public function testASaleElementWithoutAPriceIsAbsentAndTheOthersAreNot(): void
    {
        $product = $this->product();
        $unpriced = $this->factory->productSaleElement($product);

        $prices = $this->context->resolve([$unpriced->getId(), $product->getDefaultSaleElements()->getId()], $this->currency, null);

        self::assertSame([$product->getDefaultSaleElements()->getId()], array_keys($prices));
    }

    /**
     * Thirty sale elements cost the statements three do: nothing is read per sale
     * element, whatever prices them.
     */
    public function testABatchCostsNoQueryPerSaleElement(): void
    {
        $group = $this->factory->customerGroup(discount: 5.0);
        $member = $this->member($group);
        $saleElementIds = [];

        for ($i = 0; $i < 30; ++$i) {
            $product = $this->product();
            $pse = $product->getDefaultSaleElements();
            $saleElementIds[] = (int) $pse->getId();

            if (0 === $i % 3) {
                $this->factory->customerGroupProductPrice($group, $pse, $this->currency, 60.0);
            }
        }

        $rule = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 20.0]);
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);
        $sale = $this->reservedSale($product, 10.0);
        $this->factory->saleCustomer($sale, $member);

        // The three include the sale element the rule and the operation cover.
        $few = $this->statementsToResolve([$saleElementIds[0], $saleElementIds[1], $saleElementIds[29]], $member);
        $many = $this->statementsToResolve($saleElementIds, $member);

        self::assertCount(30, $this->context->resolve($saleElementIds, $this->currency, $member));
        self::assertSame(\count($few), \count($many), 'thirty sale elements cost what three cost');
        // The existence check of the request, then the prices of the group for the batch.
        self::assertSame(2, self::countSqlQueriesSelectingFrom($many, 'customer_group_product_price'));
    }

    public function testAskingAgainReadsNothing(): void
    {
        $pse = $this->saleElement();
        $member = $this->member($this->factory->customerGroup(discount: 5.0));
        $this->context->resolve([$pse->getId()], $this->currency, $member);

        $statements = $this->recordSqlQueries(function () use ($pse, $member): void {
            $this->context->resolve([$pse->getId()], $this->currency, $member);
        });

        self::assertSame([], $statements);
    }

    /**
     * @param list<int> $saleElementIds
     *
     * @return list<string>
     */
    private function statementsToResolve(array $saleElementIds, Customer $customer): array
    {
        $this->resetRequestMemos();
        // The group of the customer is read once per request, whatever the batch.
        $this->context->customerGroupOf($customer);

        return $this->recordSqlQueries(function () use ($saleElementIds, $customer): void {
            $this->context->resolve($saleElementIds, $this->currency, $customer);
        });
    }

    private function resetRequestMemos(): void
    {
        foreach ([
            CatalogActivityProbe::class,
            CustomerGroupResolver::class,
            CustomerDiscountRate::class,
            CustomerPriceContext::class,
            EffectivePriceCatalog::class,
            SaleAudienceChecker::class,
            CustomerAudience::class,
            SaleUnitContext::class,
            SaleUnitActivityChecker::class,
            SaleUnitPricingActivityChecker::class,
        ] as $service) {
            $this->getService($service)->reset();
        }
    }

    private function reservedSale(Product $product, float $percentage): Sale
    {
        $sale = $this->factory->sale([
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);
        $this->factory->saleOffsetCurrency($sale, $this->currency, $percentage);
        $this->factory->saleProduct($sale, $product);

        return $sale;
    }

    private function publicRule(Product $product, float $percentage): CatalogPriceRule
    {
        $rule = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => $percentage]);
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);

        return $rule;
    }

    private function member(CustomerGroup $group): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
    }

    private function product(): Product
    {
        return $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->currency, ['basePrice' => 100.0, 'baseQuantity' => 100]);
    }

    private function saleElement(): ProductSaleElements
    {
        return $this->product()->getDefaultSaleElements();
    }
}
