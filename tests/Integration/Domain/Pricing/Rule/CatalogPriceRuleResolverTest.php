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

namespace Thelia\Tests\Integration\Domain\Pricing\Rule;

use Thelia\Domain\Pricing\CatalogPriceResolverInterface;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleCustomerGroup;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * What the rules make of a batch for a given visitor: the stored public price for
 * everyone, and for a customer named on a rule, that rule chained with the public
 * ones - for the sale elements asked, and for nobody else.
 */
final class CatalogPriceRuleResolverTest extends ActionIntegrationTestCase
{
    use RecordsSqlQueries;

    private CatalogPriceResolverInterface $resolver;

    private RuleRepricer $repricer;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = $this->getService(CatalogPriceResolverInterface::class);
        $this->repricer = $this->getService(RuleRepricer::class);
        $this->currency = $this->factory->currency();
    }

    public function testAVisitorReadsTheStoredPublicPriceAndNothingElse(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $this->rule($product, ['percentageValue' => 20.0]);
        $this->rule($product, ['percentageValue' => 50.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS]);

        $prices = $this->resolver->resolve([$pse->getId()], $this->currency, null);

        self::assertEqualsWithDelta(80.0, $prices[$pse->getId()]->untaxedPrice, 0.000001);
    }

    /**
     * Recette dev: a reserved price is never visible to a customer who is not named
     * on it, nor to a guest account.
     */
    public function testACustomerNotNamedOnAReservedRuleGetsThePublicPrice(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $named = $this->customer();
        $other = $this->customer();
        $guest = $this->customer();
        $guest->setIsGuest(1)->save();

        $this->rule($product, ['percentageValue' => 20.0]);
        $reserved = $this->rule($product, ['percentageValue' => 50.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS, 'priority' => 10, 'stopProcessing' => true]);
        $this->factory->catalogPriceRuleCustomer($reserved, $named);
        $this->factory->catalogPriceRuleCustomer($reserved, $guest);

        self::assertEqualsWithDelta(50.0, $this->resolver->resolve([$pse->getId()], $this->currency, $named)[$pse->getId()]->untaxedPrice, 0.000001);
        self::assertEqualsWithDelta(80.0, $this->resolver->resolve([$pse->getId()], $this->currency, $other)[$pse->getId()]->untaxedPrice, 0.000001);
        self::assertEqualsWithDelta(80.0, $this->resolver->resolve([$pse->getId()], $this->currency, $guest)[$pse->getId()]->untaxedPrice, 0.000001);
    }

    /**
     * A rule reserved for a customer group prices for its members, not for another
     * customer, nor for a guest account of the group.
     */
    public function testARuleReservedForACustomerGroupPricesForItsMembersOnly(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $group = $this->factory->customerGroup();
        $member = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
        $guestMember = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
        $guestMember->setIsGuest(1)->save();

        $reserved = $this->rule($product, ['percentageValue' => 30.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMER_GROUPS]);
        (new CatalogPriceRuleCustomerGroup())->setCatalogPriceRuleId($reserved->getId())->setCustomerGroupId($group->getId())->save();

        $price = $this->resolver->resolve([$pse->getId()], $this->currency, $member)[$pse->getId()];
        self::assertEqualsWithDelta(70.0, $price->untaxedPrice, 0.000001);
        self::assertSame($reserved->getId(), $price->ruleId);
        self::assertSame([], $this->resolver->resolve([$pse->getId()], $this->currency, $this->customer()));
        self::assertSame([], $this->resolver->resolve([$pse->getId()], $this->currency, $guestMember));
    }

    public function testANamedCustomerGetsThePublicRulesAndTheirOwnChainedByPriority(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $named = $this->customer();

        $this->rule($product, ['percentageValue' => 10.0, 'priority' => 100]);
        $reserved = $this->rule($product, ['percentageValue' => 50.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS, 'priority' => 10]);
        $this->factory->catalogPriceRuleCustomer($reserved, $named);

        // 100, half for the named customer, then 10% off for everyone: 45.
        $price = $this->resolver->resolve([$pse->getId()], $this->currency, $named)[$pse->getId()];
        self::assertEqualsWithDelta(45.0, $price->untaxedPrice, 0.000001);
        self::assertNotSame($reserved->getId(), $price->ruleId, 'the public rule had the last word');
    }

    public function testAReservedRuleOutsideItsDatesOrTurnedOffDoesNotPriceForItsCustomers(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $named = $this->customer();

        $expired = $this->rule($product, ['percentageValue' => 50.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS, 'endDate' => new \DateTime('-1 day')]);
        $this->factory->catalogPriceRuleCustomer($expired, $named);
        $off = $this->rule($product, ['percentageValue' => 60.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS, 'active' => false]);
        $this->factory->catalogPriceRuleCustomer($off, $named);

        self::assertSame([], $this->resolver->resolve([$pse->getId()], $this->currency, $named));
    }

    public function testOnlyTheSaleElementsAskedForAreResolved(): void
    {
        $covered = $this->catalogProduct();
        $alsoCovered = $this->catalogProduct();
        $named = $this->customer();

        $reserved = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 50.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS]);
        $this->factory->catalogPriceRuleCriterion($reserved, CatalogPriceRule::CRITERION_PRODUCT, $covered->getId());
        $this->factory->catalogPriceRuleCriterion($reserved, CatalogPriceRule::CRITERION_PRODUCT, $alsoCovered->getId());
        $this->factory->catalogPriceRuleCustomer($reserved, $named);
        $this->repricer->afterRuleChanged($reserved);

        $asked = $this->defaultPseFor($covered)->getId();
        $prices = $this->resolver->resolve([$asked], $this->currency, $named);

        self::assertSame([$asked], array_keys($prices));
    }

    public function testASaleElementOnlyPublicRulesCoverIsPricedForANamedCustomerAsForEveryone(): void
    {
        $reservedFor = $this->catalogProduct();
        $publicOnly = $this->catalogProduct();
        $reservedPse = $this->defaultPseFor($reservedFor)->getId();
        $publicPse = $this->defaultPseFor($publicOnly)->getId();
        $named = $this->customer();

        $this->rule($reservedFor, ['percentageValue' => 20.0]);
        $this->rule($publicOnly, ['percentageValue' => 20.0]);
        $this->rule($publicOnly, ['percentageValue' => 10.0, 'startDate' => (new \DateTime('today'))->modify('+5 days')]);
        $reserved = $this->rule($reservedFor, ['percentageValue' => 50.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS]);
        $this->factory->catalogPriceRuleCustomer($reserved, $named);

        $forEveryone = $this->resolver->resolve([$reservedPse, $publicPse], $this->currency, null);
        $forTheNamedCustomer = $this->resolver->resolve([$reservedPse, $publicPse], $this->currency, $named);

        self::assertEqualsWithDelta(40.0, $forTheNamedCustomer[$reservedPse]->untaxedPrice, 0.000001);
        self::assertNotNull($forEveryone[$publicPse]->validUntil, 'the public price changes when the second public rule starts');
        self::assertEquals($forEveryone[$publicPse], $forTheNamedCustomer[$publicPse]);
    }

    public function testEverySaleElementTheReservedRuleCoversIsPricedForTheNamedCustomer(): void
    {
        $first = $this->catalogProduct();
        $second = $this->catalogProduct();
        $named = $this->customer();

        $reserved = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 50.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS]);
        $this->factory->catalogPriceRuleCriterion($reserved, CatalogPriceRule::CRITERION_PRODUCT, $first->getId());
        $this->factory->catalogPriceRuleCriterion($reserved, CatalogPriceRule::CRITERION_PRODUCT, $second->getId());
        $this->factory->catalogPriceRuleCustomer($reserved, $named);
        $this->repricer->afterRuleChanged($reserved);

        $firstPse = $this->defaultPseFor($first)->getId();
        $secondPse = $this->defaultPseFor($second)->getId();
        $prices = $this->resolver->resolve([$firstPse, $secondPse], $this->currency, $named);

        self::assertEqualsCanonicalizing([$firstPse, $secondPse], array_keys($prices));
        self::assertEqualsWithDelta(50.0, $prices[$firstPse]->untaxedPrice, 0.000001);
        self::assertEqualsWithDelta(50.0, $prices[$secondPse]->untaxedPrice, 0.000001);
    }

    public function testTheNamedPriceLastsUntilTheEarliestEndOfTheRulesThatApplied(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $named = $this->customer();
        $earliestEnd = (new \DateTime('today'))->modify('+5 days');

        // The earliest end comes neither first nor last in the order the rules apply.
        $reserved = $this->rule($product, ['percentageValue' => 10.0, 'priority' => 10, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS, 'endDate' => (new \DateTime('today'))->modify('+10 days')]);
        $this->factory->catalogPriceRuleCustomer($reserved, $named);
        $this->rule($product, ['percentageValue' => 10.0, 'priority' => 20, 'endDate' => $earliestEnd]);
        $this->rule($product, ['percentageValue' => 10.0, 'priority' => 30, 'endDate' => (new \DateTime('today'))->modify('+15 days')]);
        $this->rule($product, ['percentageValue' => 10.0, 'priority' => 40]);

        $price = $this->resolver->resolve([$pse->getId()], $this->currency, $named)[$pse->getId()];

        self::assertEqualsWithDelta(65.61, $price->untaxedPrice, 0.000001, 'the four rules applied');
        self::assertNotNull($price->validUntil);
        self::assertSame($earliestEnd->getTimestamp(), $price->validUntil->getTimestamp());
    }

    public function testAReservedRuleWithoutAValueInTheCurrencyTakesNoPartInTheNamedPrice(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $named = $this->customer();

        $public = $this->rule($product, ['percentageValue' => 20.0]);
        $withoutValue = $this->rule($product, ['effectType' => CatalogPriceRule::EFFECT_TYPE_AMOUNT, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS, 'priority' => 10]);
        $this->factory->catalogPriceRuleCustomer($withoutValue, $named);
        $withValue = $this->rule($product, ['percentageValue' => 50.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS, 'priority' => 20]);
        $this->factory->catalogPriceRuleCustomer($withValue, $named);

        $price = $this->resolver->resolve([$pse->getId()], $this->currency, $named)[$pse->getId()];

        // 100, half for the named customer, then 20% off for everyone: the stored public price would be 80.
        self::assertEqualsWithDelta(40.0, $price->untaxedPrice, 0.000001);
        self::assertSame($public->getId(), $price->ruleId);
    }

    public function testASaleElementWithoutAPriceGetsNoReservedPriceAndTheOthersStillDo(): void
    {
        // The unpriced sale element sits between two priced ones, whichever order the scope is read in.
        $pricedBefore = $this->catalogProduct();
        $unpriced = $this->catalogProduct();
        $pricedAfter = $this->catalogProduct();
        $pricedBeforePse = $this->defaultPseFor($pricedBefore)->getId();
        $unpricedPse = $this->defaultPseFor($unpriced)->getId();
        $pricedAfterPse = $this->defaultPseFor($pricedAfter)->getId();
        $named = $this->customer();

        $reserved = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 50.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS]);

        foreach ([$pricedBefore, $unpriced, $pricedAfter] as $product) {
            $this->factory->catalogPriceRuleCriterion($reserved, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        }

        $this->factory->catalogPriceRuleCustomer($reserved, $named);
        $this->repricer->afterRuleChanged($reserved);
        ProductPriceQuery::create()->filterByProductSaleElementsId($unpricedPse)->delete();

        $prices = $this->resolver->resolve([$pricedBeforePse, $unpricedPse, $pricedAfterPse], $this->currency, $named);

        self::assertEqualsCanonicalizing([$pricedBeforePse, $pricedAfterPse], array_keys($prices));
        self::assertEqualsWithDelta(50.0, $prices[$pricedBeforePse]->untaxedPrice, 0.000001);
        self::assertEqualsWithDelta(50.0, $prices[$pricedAfterPse]->untaxedPrice, 0.000001);
    }

    /**
     * Most shops have no reserved rule for most of their customers: once the checks a
     * request makes only once are answered, their pages cost the read of the stored
     * prices and nothing more.
     */
    public function testACustomerEntitledToNoReservedRuleReadsTheStoredPricesInOneStatement(): void
    {
        $first = $this->catalogProduct();
        $second = $this->catalogProduct();
        $firstPse = $this->defaultPseFor($first)->getId();
        $secondPse = $this->defaultPseFor($second)->getId();
        $customer = $this->customer();

        $this->rule($first, ['percentageValue' => 20.0]);
        $this->rule($second, ['percentageValue' => 20.0]);
        $reservedForSomeoneElse = $this->rule($first, ['percentageValue' => 50.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS]);
        $this->factory->catalogPriceRuleCustomer($reservedForSomeoneElse, $this->customer());

        // A first resolution answers what is asked once per request: rules running, customer entitlements.
        $this->resolver->resolve([$firstPse, $secondPse], $this->currency, $customer);
        $prices = [];
        $statements = $this->recordSqlQueries(function () use ($firstPse, $secondPse, $customer, &$prices): void {
            $prices = $this->resolver->resolve([$firstPse, $secondPse], $this->currency, $customer);
        });

        self::assertEqualsCanonicalizing([$firstPse, $secondPse], array_keys($prices));
        self::assertEqualsWithDelta(80.0, $prices[$firstPse]->untaxedPrice, 0.000001);
        self::assertEqualsWithDelta(80.0, $prices[$secondPse]->untaxedPrice, 0.000001);
        self::assertCount(1, $statements);
        self::assertSame(1, self::countSqlQueriesSelectingFrom($statements, 'catalog_price_rule_price'));
    }

    /**
     * A customer named only on a rule that has stopped costs no read of the scope:
     * there is nothing of theirs to price on the spot.
     */
    public function testACustomerWhoseReservedRuleIsNotRunningCostsNoScopeRead(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product)->getId();
        $named = $this->customer();

        $this->rule($product, ['percentageValue' => 20.0]);
        $expired = $this->rule($product, ['percentageValue' => 50.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS, 'endDate' => new \DateTime('-1 day')]);
        $this->factory->catalogPriceRuleCustomer($expired, $named);

        // A first resolution answers what is asked once per request: rules running, customer entitlements.
        $this->resolver->resolve([$pse], $this->currency, $named);
        $prices = [];
        $statements = $this->recordSqlQueries(function () use ($pse, $named, &$prices): void {
            $prices = $this->resolver->resolve([$pse], $this->currency, $named);
        });

        self::assertEqualsWithDelta(80.0, $prices[$pse]->untaxedPrice, 0.000001);
        self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'catalog_price_rule_product_sale_elements'));
    }

    private function rule(Product $product, array $overrides): CatalogPriceRule
    {
        $rule = $this->factory->catalogPriceRule($overrides + ['active' => true]);
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $this->repricer->afterRuleChanged($rule);

        return $rule;
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    private function catalogProduct(): Product
    {
        return $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->currency, ['basePrice' => 100.0]);
    }

    private function defaultPseFor(Product $product): ProductSaleElements
    {
        $pse = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(true)->findOne();
        self::assertNotNull($pse);

        return $pse;
    }
}
