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

namespace Thelia\Tests\Integration\Core\Template;

use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Pricing\CurrencyConverter;
use Thelia\Domain\SaleUnit\Pricing\SaleUnitPriceResolver;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Trait\ResetsPriceMemos;

/**
 * A default currency price is converted at `rate / default rate` wherever it is read: the
 * SQL of the loops, the cart and the sale unit prices agree to the last digit, whatever the
 * rate of the default currency is.
 *
 * Default currency at rate 2, a second one at rate 3: a price of 100 in the default
 * currency is 150 in the second one - not 300, what the rate alone gives.
 */
final class ProductLoopCurrencyConversionTest extends ActionIntegrationTestCase
{
    use ResetsPriceMemos;

    private Currency $defaultCurrency;

    private Currency $other;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultCurrency = Currency::getDefaultCurrency();
        $this->defaultCurrency->setRate(2.0)->save($this->getPropelConnection());
        $this->other = $this->factory->currency(['code' => 'QQQ', 'symbol' => 'Q', 'rate' => 3.0, 'byDefault' => 0]);
        $this->product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->defaultCurrency, ['basePrice' => 100.0, 'baseQuantity' => 100, 'title' => 'Converted product']);
        $this->resetPriceMemos();
    }

    protected function tearDown(): void
    {
        $this->getService(SaleUnitContext::class)->reset();
        $this->resetPriceMemos();
        Currency::resetDefaultCurrencyCache();

        parent::tearDown();
    }

    public function testTheProductLoopPricesInTheSecondCurrencyWhatTheCartDoes(): void
    {
        $row = $this->firstRow('product', ['id' => $this->product->getId(), 'currency' => $this->other->getId()]);

        self::assertEqualsWithDelta(150.0, (float) $row['PRICE'], 0.000001);
        self::assertEqualsWithDelta(150.0, (float) $row['BEST_PRICE'], 0.000001);
        self::assertEqualsWithDelta($this->cartLinePriceInTheSecondCurrency(), (float) $row['PRICE'], 0.000001);
    }

    public function testTheProductLoopConvertsThePromoPriceToo(): void
    {
        $saleElements = $this->product->getDefaultSaleElements();
        $saleElements->setPromo(1)->save($this->getPropelConnection());
        $price = ProductPriceQuery::create()->filterByProductSaleElementsId($saleElements->getId())->findOne();
        $price->setPromoPrice('80.000000')->save($this->getPropelConnection());

        $row = $this->firstRow('product', ['id' => $this->product->getId(), 'currency' => $this->other->getId()]);

        self::assertEqualsWithDelta(120.0, (float) $row['PROMO_PRICE'], 0.000001);
        self::assertEqualsWithDelta(120.0, (float) $row['BEST_PRICE'], 0.000001);
    }

    public function testTheSaleElementLoopPricesInTheSecondCurrencyWhatTheCartDoes(): void
    {
        $row = $this->firstRow('product_sale_elements', ['id' => $this->product->getDefaultSaleElements()->getId(), 'currency' => $this->other->getId()]);

        self::assertEqualsWithDelta(150.0, (float) $row['PRICE'], 0.000001);
        self::assertEqualsWithDelta($this->cartLinePriceInTheSecondCurrency(), (float) $row['PRICE'], 0.000001);
    }

    public function testThePriceFiltersOfTheSimpleLoopCompareTheConvertedPrice(): void
    {
        $arguments = ['id' => $this->product->getId(), 'currency' => $this->other->getId()];

        self::assertNotNull($this->firstRow('product', $arguments + ['max_price' => 160]));
        self::assertNull($this->firstRow('product', $arguments + ['max_price' => 140]));
        self::assertNotNull($this->firstRow('product', $arguments + ['min_price' => 140]));
        self::assertNull($this->firstRow('product', $arguments + ['min_price' => 160]));
    }

    public function testThePriceFiltersOfTheComplexLoopCompareTheConvertedPrice(): void
    {
        $arguments = ['id' => $this->product->getId(), 'currency' => $this->other->getId(), 'complex' => 1];

        self::assertNotNull($this->firstRow('product', $arguments + ['max_price' => 160]));
        self::assertNull($this->firstRow('product', $arguments + ['max_price' => 140]));
        self::assertNotNull($this->firstRow('product', $arguments + ['min_price' => 140]));
        self::assertNull($this->firstRow('product', $arguments + ['min_price' => 160]));
    }

    public function testTheComplexLoopPricesInTheSecondCurrencyWhatTheCartDoes(): void
    {
        $row = $this->firstRow('product', ['id' => $this->product->getId(), 'currency' => $this->other->getId(), 'complex' => 1]);

        self::assertEqualsWithDelta($this->cartLinePriceInTheSecondCurrency(), (float) $row['BEST_PRICE'], 0.000001);
    }

    public function testThePriceOrderSeesTheConvertedPrices(): void
    {
        $cheaper = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->defaultCurrency, ['basePrice' => 60.0, 'baseQuantity' => 100, 'title' => 'Cheaper converted product']);
        $ids = $this->product->getId().','.$cheaper->getId();

        $prices = [];

        foreach ($this->getService(LoopExecutor::class)->execute('product', ['id' => $ids, 'currency' => $this->other->getId(), 'order' => 'min_price']) as $row) {
            $prices[(int) $row->getVarVal()['ID']] = (float) $row->getVarVal()['PRICE'];
        }

        self::assertSame([$cheaper->getId(), $this->product->getId()], array_keys($prices));
        self::assertEqualsWithDelta(90.0, $prices[$cheaper->getId()], 0.000001);
    }

    public function testTheSaleUnitPriceDerivedFromTheDefaultCurrencyIsConvertedTheSameWay(): void
    {
        $saleElements = $this->product->getDefaultSaleElements();
        $saleUnit = $this->factory->saleUnit();
        $this->factory->saleUnitProductPrice($saleUnit, $saleElements, $this->defaultCurrency, 50.0);
        $this->getService(SaleUnitContext::class)->setSaleUnit($saleUnit);

        $prices = $this->getService(SaleUnitPriceResolver::class)->resolve([$saleElements->getId()], $this->other);

        self::assertEqualsWithDelta(75.0, $prices[$saleElements->getId()], 0.000001);
    }

    public function testTheConverterAndItsFactorFollowTheDefaultRate(): void
    {
        $converter = $this->getService(CurrencyConverter::class);

        self::assertEqualsWithDelta(1.5, $converter->factor($this->other), 0.000000001);
        self::assertEqualsWithDelta(1.0, $converter->factor($this->defaultCurrency), 0.000000001);
        self::assertEqualsWithDelta(150.0, $converter->fromDefaultCurrency(100.0, $this->other), 0.000001);
        self::assertSame('1.500000000000', $converter->factorAsSql($this->other));
    }

    public function testWithoutAUsableDefaultRateNothingConverts(): void
    {
        $this->defaultCurrency->setRate(0.0)->save($this->getPropelConnection());
        $converter = $this->getService(CurrencyConverter::class);

        self::assertNull($converter->factor($this->other));
        self::assertNull($converter->fromDefaultCurrency(100.0, $this->other));
        self::assertSame('NULL', $converter->factorAsSql($this->other));
    }

    private function cartLinePriceInTheSecondCurrency(): float
    {
        $cart = $this->factory->cart(null, ['currency' => $this->other]);
        $saleElements = $this->product->getDefaultSaleElements();

        $event = new CartEvent($cart);
        $event
            ->setProductId($this->product->getId())
            ->setProductSaleElementsId($saleElements->getId())
            ->setQuantity(1)
            ->setNewness(true)
            ->setAppend(true);
        $this->dispatch($event, TheliaEvents::CART_ADDITEM);

        $line = CartItemQuery::create()->filterByCartId($cart->getId())->findOne() ?? self::fail('The line was not added.');

        return $line->getRealPrice();
    }

    private function firstRow(string $loop, array $arguments): ?array
    {
        foreach ($this->getService(LoopExecutor::class)->execute($loop, $arguments) as $row) {
            return $row->getVarVal();
        }

        return null;
    }
}
