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
use Thelia\Domain\Pricing\Rule\Exception\InvalidCatalogPriceRuleException;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleCustomerQuery;
use Thelia\Model\CatalogPriceRuleProductSaleElementsQuery;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Model\Product;
use Thelia\Model\Sale;
use Thelia\Model\SaleOffsetCurrency;
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

    public function testTheRulesComeOutWithTheSettingsOfANewRule(): void
    {
        $sale = $this->sale();
        $this->factory->saleProduct($sale, $this->catalogProduct());
        $this->factory->saleOffsetCurrency($sale, $this->currency, 20.0);

        $rule = $this->converter->convert($sale)->rules[0];

        self::assertSame(100, (int) $rule->getPriority());
        self::assertFalse((bool) $rule->getStopProcessing(), 'the rules after it still apply');
        self::assertTrue((bool) $rule->getIncludeSubcategories());
    }

    public function testAProductNarrowedToSeveralAttributeValuesKeepsThemAll(): void
    {
        $size = $this->factory->attribute();
        $small = $this->factory->attributeAv($size);
        $large = $this->factory->attributeAv($size);
        $product = $this->catalogProduct();
        $sale = $this->sale();
        $this->factory->saleProduct($sale, $product, $small);
        $this->factory->saleProduct($sale, $product, $large);
        $this->factory->saleOffsetCurrency($sale, $this->currency, 20.0);

        $result = $this->converter->convert($sale);

        self::assertCount(1, $result->rules);
        self::assertEqualsCanonicalizing([$small->getId(), $large->getId()], $result->rules[0]->getCriteriaByType()[CatalogPriceRule::CRITERION_ATTRIBUTE_AV]);
    }

    public function testProductsNarrowedToTheSameAttributeValuesInAnotherOrderShareOneRule(): void
    {
        $size = $this->factory->attribute();
        $small = $this->factory->attributeAv($size);
        $large = $this->factory->attributeAv($size);
        $first = $this->catalogProduct();
        $second = $this->catalogProduct();
        $sale = $this->sale();
        $this->factory->saleProduct($sale, $first, $large);
        $this->factory->saleProduct($sale, $first, $small);
        $this->factory->saleProduct($sale, $second, $small);
        $this->factory->saleProduct($sale, $second, $large);
        $this->factory->saleOffsetCurrency($sale, $this->currency, 20.0);

        $result = $this->converter->convert($sale);

        self::assertCount(1, $result->rules);
        self::assertSame([], $result->warnings);
        self::assertEqualsCanonicalizing([$first->getId(), $second->getId()], $result->rules[0]->getCriteriaByType()[CatalogPriceRule::CRITERION_PRODUCT]);
    }

    public function testAnAttributeValueListedTwiceForAProductCountsOnce(): void
    {
        $size = $this->factory->attribute();
        $large = $this->factory->attributeAv($size);
        $first = $this->catalogProduct();
        $second = $this->catalogProduct();
        $sale = $this->sale();
        $this->factory->saleProduct($sale, $first, $large);
        $this->factory->saleProduct($sale, $first, $large);
        $this->factory->saleProduct($sale, $second, $large);
        $this->factory->saleOffsetCurrency($sale, $this->currency, 20.0);

        $result = $this->converter->convert($sale);

        self::assertCount(1, $result->rules);
        self::assertEqualsCanonicalizing([$first->getId(), $second->getId()], $result->rules[0]->getCriteriaByType()[CatalogPriceRule::CRITERION_PRODUCT]);
    }

    public function testTheTitleIsReadInTheDefaultLanguageWhateverLanguageTheSaleWasLastReadIn(): void
    {
        $defaultLocale = Lang::getDefaultLanguage()->getLocale();
        $otherLocale = 'fr_FR' === $defaultLocale ? 'de_DE' : 'fr_FR';
        $sale = $this->sale(['locale' => $defaultLocale, 'title' => 'Winter sale']);
        $sale->setLocale($otherLocale)->setTitle('Soldes d\'hiver')->save();
        $this->factory->saleProduct($sale, $this->catalogProduct());
        $this->factory->saleOffsetCurrency($sale, $this->currency, 20.0);

        $rule = $this->converter->convert($sale)->rules[0];

        self::assertSame('Winter sale', $rule->setLocale($defaultLocale)->getTitle());
    }

    public function testASaleWithoutATitleInTheDefaultLanguageIsRefusedWithASentence(): void
    {
        $defaultLocale = Lang::getDefaultLanguage()->getLocale();
        $sale = $this->sale(['locale' => 'fr_FR' === $defaultLocale ? 'de_DE' : 'fr_FR', 'title' => 'Soldes']);
        $this->factory->saleProduct($sale, $this->catalogProduct());
        $this->factory->saleOffsetCurrency($sale, $this->currency, 20.0);

        $this->expectException(InvalidCatalogPriceRuleException::class);
        $this->expectExceptionMessage('A catalog price rule needs a title.');

        $this->converter->convert($sale);
    }

    public function testAPercentageLeftEmptyIsRefusedWithASentence(): void
    {
        $sale = $this->sale();
        $this->factory->saleProduct($sale, $this->catalogProduct());
        (new SaleOffsetCurrency())->setSaleId($sale->getId())->setCurrencyId($this->currency->getId())->setPriceOffsetValue(null)->save();

        $this->expectException(InvalidCatalogPriceRuleException::class);
        $this->expectExceptionMessage('A percentage rule needs a percentage between 0 and 100.');

        $this->converter->convert($sale);
    }

    /**
     * The only case where the default currency carries no percentage at all: every
     * other sale here types one in it.
     */
    public function testAPercentageTypedOnlyInAnotherCurrencyIsTaken(): void
    {
        $secondary = $this->factory->currency(['code' => 'CHF', 'symbol' => 'CHF', 'rate' => 1.1]);
        self::assertNotSame(Currency::getDefaultCurrency()->getId(), $secondary->getId());
        $sale = $this->sale();
        $this->factory->saleProduct($sale, $this->catalogProduct());
        $this->factory->saleOffsetCurrency($sale, $secondary, 15.0);

        $result = $this->converter->convert($sale);

        self::assertEqualsWithDelta(15.0, (float) $result->rules[0]->getPercentageValue(), 0.0001);
        self::assertSame([], $result->warnings);
    }

    public function testThePercentageOfTheDefaultCurrencyWinsWhereverItComesInTheList(): void
    {
        $formerDefault = Currency::getDefaultCurrency();
        $newDefault = $this->factory->currency(['code' => 'CHF', 'symbol' => 'CHF', 'rate' => 1.1, 'byDefault' => 1]);
        $formerDefault->setByDefault(0)->save();
        (new \ReflectionProperty(Currency::class, 'defaultCurrency'))->setValue(null, null);
        self::assertSame($newDefault->getId(), Currency::getDefaultCurrency()->getId());
        self::assertLessThan($newDefault->getId(), $formerDefault->getId(), 'the default currency comes second in the list');

        $sale = $this->sale();
        $this->factory->saleProduct($sale, $this->catalogProduct());
        $this->factory->saleOffsetCurrency($sale, $formerDefault, 20.0);
        $this->factory->saleOffsetCurrency($sale, $newDefault, 10.0);

        $result = $this->converter->convert($sale);

        self::assertEqualsWithDelta(10.0, (float) $result->rules[0]->getPercentageValue(), 0.0001);
        self::assertCount(1, $result->warnings);
    }

    public function testTheSamePercentageInEveryCurrencyGivesNoWarning(): void
    {
        $sale = $this->saleWithPercentages(20.0, 20.0);

        self::assertSame([], $this->converter->convert($sale)->warnings);
    }

    public function testPercentagesApartBeyondTheStoredPrecisionGiveNoWarning(): void
    {
        $sale = $this->saleWithPercentages(1.00001, 1.00002);

        self::assertSame([], $this->converter->convert($sale)->warnings);
    }

    public function testPercentagesApartAtTheStoredPrecisionGiveAWarning(): void
    {
        $sale = $this->saleWithPercentages(10.0001, 10.0002);

        self::assertSame(
            ['The sale typed a different percentage per currency; the rule takes the percentage of the default currency for every currency.'],
            $this->converter->convert($sale)->warnings,
        );
    }

    private function saleWithPercentages(float $inTheDefaultCurrency, float $inAnother): Sale
    {
        $secondary = $this->factory->currency(['code' => 'CHF', 'symbol' => 'CHF', 'rate' => 1.1]);
        $sale = $this->sale();
        $this->factory->saleProduct($sale, $this->catalogProduct());
        $this->factory->saleOffsetCurrency($sale, Currency::getDefaultCurrency(), $inTheDefaultCurrency);
        $this->factory->saleOffsetCurrency($sale, $secondary, $inAnother);

        return $sale;
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
