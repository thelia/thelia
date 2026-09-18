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
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * What the rules make of a batch for a given visitor: the stored public price for
 * everyone, and for a customer named on a rule, that rule chained with the public
 * ones - for the sale elements asked, and for nobody else.
 */
final class CatalogPriceRuleResolverTest extends ActionIntegrationTestCase
{
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
