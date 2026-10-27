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

namespace Thelia\Tests\Integration\Model;

use Thelia\Domain\Pricing\CurrencyConverter;
use Thelia\Model\Currency;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The prices of a sale element in a currency: the one typed in that currency, or the default
 * currency one converted by the one conversion of the shop - the one the customer price
 * context applies, so that the cart and the catalog agree to the last digit.
 */
final class ProductSaleElementsPricesByCurrencyTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private Currency $defaultCurrency;

    private ProductSaleElements $saleElements;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->defaultCurrency = Currency::getDefaultCurrency();
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->defaultCurrency, ['basePrice' => 100.0, 'baseQuantity' => 1]);
        $this->saleElements = $product->getDefaultSaleElements();
        ProductPriceQuery::create()->filterByProductSaleElementsId($this->saleElements->getId())->findOne()->setPromoPrice('80.000000')->save($this->getPropelConnection());
    }

    public function testTheDefaultCurrencyReadsItsOwnRow(): void
    {
        $prices = $this->saleElements->getPricesByCurrency($this->defaultCurrency);

        self::assertSame(100.0, $prices->getPrice());
        self::assertSame(80.0, $prices->getPromoPrice());
    }

    public function testAnotherCurrencyWithoutARowOfItsOwnIsConvertedAtTheRateOfTheShop(): void
    {
        $other = $this->factory->currency(['code' => 'QQQ', 'symbol' => 'Q', 'rate' => 2.0, 'byDefault' => 0]);
        $converter = new CurrencyConverter();

        $prices = $this->saleElements->getPricesByCurrency($other);

        self::assertEqualsWithDelta($converter->fromDefaultCurrency(100.0, $other), $prices->getPrice(), 0.0000001);
        self::assertEqualsWithDelta($converter->fromDefaultCurrency(80.0, $other), $prices->getPromoPrice(), 0.0000001);
        self::assertEqualsWithDelta(100.0 * 2.0 / (float) $this->defaultCurrency->getRate(), $prices->getPrice(), 0.0000001);
    }

    public function testARowFlaggedAsDerivedFromTheDefaultCurrencyIsConvertedToo(): void
    {
        $other = $this->factory->currency(['code' => 'QQQ', 'symbol' => 'Q', 'rate' => 2.0, 'byDefault' => 0]);
        $this->factory->productPrice($this->saleElements, $other, ['price' => '0.000000', 'promoPrice' => '0.000000', 'fromDefaultCurrency' => true]);

        self::assertEqualsWithDelta(100.0 * 2.0 / (float) $this->defaultCurrency->getRate(), $this->saleElements->getPricesByCurrency($other)->getPrice(), 0.0000001);
    }

    public function testARowTypedInTheCurrencyIsTakenAsIs(): void
    {
        $other = $this->factory->currency(['code' => 'QQQ', 'symbol' => 'Q', 'rate' => 2.0, 'byDefault' => 0]);
        $this->factory->productPrice($this->saleElements, $other, ['price' => '123.000000', 'promoPrice' => '99.000000']);

        $prices = $this->saleElements->getPricesByCurrency($other);

        self::assertSame(123.0, $prices->getPrice());
        self::assertSame(99.0, $prices->getPromoPrice());
    }

    public function testASaleElementPricedInNoCurrencyStillFailsAtThisLevel(): void
    {
        ProductPriceQuery::create()->filterByProductSaleElementsId($this->saleElements->getId())->delete($this->getPropelConnection());

        $this->expectException(\RuntimeException::class);

        $this->saleElements->getPricesByCurrency($this->defaultCurrency);
    }
}
