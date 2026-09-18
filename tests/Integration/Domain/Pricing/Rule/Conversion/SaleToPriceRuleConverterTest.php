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

namespace Thelia\Tests\Integration\Domain\Pricing\Rule\Conversion;

use Thelia\Domain\Pricing\Rule\Conversion\SaleToPriceRuleConverter;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleCustomerQuery;
use Thelia\Model\CatalogPriceRuleProductSaleElementsQuery;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\Sale;
use Thelia\Model\SaleQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A flash sale becomes rules the merchant can turn on, and is itself left alone.
 */
final class SaleToPriceRuleConverterTest extends ActionIntegrationTestCase
{
    private SaleToPriceRuleConverter $converter;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->converter = $this->getService(SaleToPriceRuleConverter::class);
        $this->currency = $this->factory->currency();
    }

    public function testAPercentageSaleOnWholeProductsBecomesOneTurnedOffRule(): void
    {
        $productA = $this->catalogProduct();
        $productB = $this->catalogProduct();
        $sale = $this->sale(['title' => 'Winter sale', 'active' => true]);
        $this->factory->saleProduct($sale, $productA);
        $this->factory->saleProduct($sale, $productB);
        $this->factory->saleOffsetCurrency($sale, $this->currency, 20.0);

        $result = $this->converter->convert($sale);

        self::assertCount(1, $result->rules);
        self::assertSame([], $result->warnings);
        $rule = $result->rules[0];
        self::assertSame('Winter sale', $rule->setLocale('en_US')->getTitle());
        self::assertFalse((bool) $rule->getActive(), 'the rule comes out turned off');
        self::assertSame(CatalogPriceRule::EFFECT_TYPE_PERCENTAGE, (int) $rule->getEffectType());
        self::assertEqualsWithDelta(20.0, (float) $rule->getPercentageValue(), 0.0001);
        self::assertSame($sale->getStartDate()?->format('c'), $rule->getStartDate()?->format('c'));
        self::assertSame($sale->getEndDate()?->format('c'), $rule->getEndDate()?->format('c'));
        self::assertEqualsCanonicalizing([$productA->getId(), $productB->getId()], $rule->getCriteriaByType()[CatalogPriceRule::CRITERION_PRODUCT]);
        self::assertSame(2, CatalogPriceRuleProductSaleElementsQuery::create()->filterByCatalogPriceRuleId($rule->getId())->count(), 'the scope is materialized at once');

        self::assertTrue((bool) SaleQuery::create()->findPk($sale->getId())->getActive(), 'the sale is left exactly as it was');
    }

    public function testAnAmountSaleKeepsOneValuePerCurrency(): void
    {
        $secondary = $this->factory->currency(['code' => 'CHF', 'symbol' => 'CHF', 'rate' => 1.1]);
        $product = $this->catalogProduct();
        $sale = $this->sale(['priceOffsetType' => Sale::OFFSET_TYPE_AMOUNT]);
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleOffsetCurrency($sale, $this->currency, 10.0);
        $this->factory->saleOffsetCurrency($sale, $secondary, 12.0);

        $rule = $this->converter->convert($sale)->rules[0];

        self::assertSame(CatalogPriceRule::EFFECT_TYPE_AMOUNT, (int) $rule->getEffectType());
        self::assertEqualsWithDelta(10.0, $rule->getEffectValuesByCurrency()[$this->currency->getId()], 0.0001);
        self::assertEqualsWithDelta(12.0, $rule->getEffectValuesByCurrency()[$secondary->getId()], 0.0001);
    }

    public function testProductsNarrowedToAttributeValuesBecomeRulesOfTheirOwn(): void
    {
        $size = $this->factory->attribute();
        $large = $this->factory->attributeAv($size);
        $whole = $this->catalogProduct();
        $narrowed = $this->catalogProduct();
        $sale = $this->sale(['title' => 'Mixed']);
        $this->factory->saleProduct($sale, $whole);
        $this->factory->saleProduct($sale, $narrowed, $large);
        $this->factory->saleOffsetCurrency($sale, $this->currency, 20.0);

        $result = $this->converter->convert($sale);

        self::assertCount(2, $result->rules);
        self::assertCount(1, $result->warnings);
        [$wholeRule, $narrowedRule] = $result->rules;
        self::assertSame('Mixed (1)', $wholeRule->setLocale('en_US')->getTitle());
        self::assertArrayNotHasKey(CatalogPriceRule::CRITERION_ATTRIBUTE_AV, $wholeRule->getCriteriaByType());
        self::assertSame([$large->getId()], $narrowedRule->getCriteriaByType()[CatalogPriceRule::CRITERION_ATTRIBUTE_AV]);
        self::assertSame([$narrowed->getId()], $narrowedRule->getCriteriaByType()[CatalogPriceRule::CRITERION_PRODUCT]);
    }

    public function testAReservedSaleBecomesAReservedRuleWithTheSameCustomers(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->factory->customer($this->factory->customerTitle());
        $sale = $this->sale(['audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS]);
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleCustomer($sale, $customer);
        $this->factory->saleOffsetCurrency($sale, $this->currency, 20.0);

        $rule = $this->converter->convert($sale, 'VIP')->rules[0];

        self::assertSame(CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS, (int) $rule->getAudienceMode());
        self::assertSame([$customer->getId()], array_map('intval', CatalogPriceRuleCustomerQuery::create()->filterByCatalogPriceRuleId($rule->getId())->select('CustomerId')->find()->getData()));
        self::assertSame('VIP', $rule->setLocale('en_US')->getTitle());
    }

    private function sale(array $overrides = []): Sale
    {
        return $this->factory->sale($overrides + [
            'active' => false,
            'priceOffsetType' => Sale::OFFSET_TYPE_PERCENTAGE,
            'startDate' => new \DateTime('2030-01-01 00:00:00'),
            'endDate' => new \DateTime('2030-02-01 00:00:00'),
        ]);
    }

    private function catalogProduct(): Product
    {
        return $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->currency, ['basePrice' => 100.0]);
    }
}
