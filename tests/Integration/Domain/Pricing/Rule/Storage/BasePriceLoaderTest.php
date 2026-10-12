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

namespace Thelia\Tests\Integration\Domain\Pricing\Rule\Storage;

use Thelia\Domain\Pricing\Rule\Storage\BasePriceLoader;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The catalog price a rule starts from, in every currency: the price typed in the
 * currency, or the default currency price converted at the rates.
 */
final class BasePriceLoaderTest extends ActionIntegrationTestCase
{
    private BasePriceLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loader = $this->getService(BasePriceLoader::class);
    }

    public function testAPriceTypedInACurrencyIsReadAsTypedAndTheOtherCurrenciesAreConverted(): void
    {
        $typed = $this->factory->currency(['code' => 'XTS', 'symbol' => 'X', 'rate' => 3.0, 'visible' => 1]);
        $converted = $this->factory->currency(['code' => 'XTT', 'symbol' => 'Y', 'rate' => 2.0, 'visible' => 0]);
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $this->factory->productPrice($pse, $typed, ['price' => '150.000000']);

        $prices = $this->loader->load([$pse->getId()])[$pse->getId()];

        self::assertSame($product->getId(), $prices->productId);
        self::assertEqualsWithDelta(100.0, $prices->in((int) Currency::getDefaultCurrency()->getId()), 0.000001);
        self::assertEqualsWithDelta(150.0, $prices->in((int) $typed->getId()), 0.000001);
        self::assertEqualsWithDelta(100.0 * 2.0 / $this->defaultRate(), $prices->in((int) $converted->getId()), 0.000001);
    }

    public function testARowFlaggedAsConvertedIsConvertedAgainFromTheDefaultCurrency(): void
    {
        $currency = $this->factory->currency(['code' => 'XTS', 'symbol' => 'X', 'rate' => 3.0, 'visible' => 1]);
        $pse = $this->defaultPseFor($this->catalogProduct());
        $this->factory->productPrice($pse, $currency, ['price' => '999.000000', 'fromDefaultCurrency' => true]);

        $prices = $this->loader->load([$pse->getId()])[$pse->getId()];

        self::assertEqualsWithDelta(100.0 * 3.0 / $this->defaultRate(), $prices->in((int) $currency->getId()), 0.000001);
    }

    public function testTheConversionDividesByTheRateOfTheDefaultCurrency(): void
    {
        Currency::getDefaultCurrency()->setRate(2.0)->save();
        $currency = $this->factory->currency(['code' => 'XTS', 'symbol' => 'X', 'rate' => 3.0, 'visible' => 1]);
        $pse = $this->defaultPseFor($this->catalogProduct());

        $prices = $this->loader->load([$pse->getId()])[$pse->getId()];

        self::assertEqualsWithDelta(150.0, $prices->in((int) $currency->getId()), 0.000001);
    }

    public function testADefaultCurrencyWithoutARateConvertsNothing(): void
    {
        Currency::getDefaultCurrency()->setRate(0.0)->save();
        $currency = $this->factory->currency(['code' => 'XTS', 'symbol' => 'X', 'rate' => 3.0, 'visible' => 1]);
        $pse = $this->defaultPseFor($this->catalogProduct());

        $prices = $this->loader->load([$pse->getId()])[$pse->getId()];

        self::assertEqualsWithDelta(100.0, $prices->in((int) Currency::getDefaultCurrency()->getId()), 0.000001);
        self::assertNull($prices->in((int) $currency->getId()));
    }

    /**
     * The rows come in the order the database answers them, with no ORDER BY: the
     * unpriced sale element is created between two priced ones, so it sits in the
     * middle whether that order is ascending or descending.
     */
    public function testASaleElementWithoutAnyPriceDoesNotStopTheOtherSaleElements(): void
    {
        $firstProduct = $this->catalogProduct();
        $first = $this->defaultPseFor($firstProduct);
        $unpriced = $this->factory->productSaleElement($firstProduct);
        $second = $this->defaultPseFor($this->catalogProduct());

        $prices = $this->loader->load([$first->getId(), $unpriced->getId(), $second->getId()]);

        self::assertSame($firstProduct->getId(), $prices[$unpriced->getId()]->productId);
        self::assertSame([], $prices[$unpriced->getId()]->untaxedPriceByCurrencyId);

        foreach ([$first, $second] as $priced) {
            self::assertArrayHasKey($priced->getId(), $prices);
            self::assertEqualsWithDelta(100.0, $prices[$priced->getId()]->in((int) Currency::getDefaultCurrency()->getId()), 0.000001);
        }
    }

    public function testAnIdThatIsNotAnIntegerNeverWidensTheStatement(): void
    {
        $pse = $this->defaultPseFor($this->catalogProduct());
        $this->catalogProduct();

        self::assertSame([$pse->getId()], array_keys($this->loader->load([$pse->getId().') OR (1 = 1'])));
    }

    private function defaultRate(): float
    {
        return (float) Currency::getDefaultCurrency()->getRate();
    }

    private function catalogProduct(): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            Currency::getDefaultCurrency(),
            ['baseQuantity' => 100, 'basePrice' => 100.0],
        );
    }

    private function defaultPseFor(Product $product): ProductSaleElements
    {
        $pse = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(true)->findOne();
        self::assertNotNull($pse);

        return $pse;
    }
}
