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

namespace Thelia\Tests\Integration\Domain\SaleUnit;

use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Exception\NotEnoughStockException;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\SaleUnit;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The cart offers what the sale unit the visitor buys from can sell: an addition or
 * a new quantity beyond the unit's own stock is refused even when the national
 * counter would cover it, and a quantity the unit holds is accepted even when the
 * national counter is short. A shop that resolves no unit is judged on the
 * national counter, as before.
 */
final class SaleUnitCartStockTest extends ActionIntegrationTestCase
{
    private Product $product;

    private ProductSaleElements $productSaleElements;

    private SaleUnit $saleUnit;

    private Cart $cart;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check-available-stock', '1');

        $currency = $this->factory->currency();
        $this->product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency);
        $this->productSaleElements = $this->product->getDefaultSaleElements();
        $this->productSaleElements->setQuantity(100)->save($this->getPropelConnection());
        $this->saleUnit = $this->factory->saleUnit();
        $this->cart = $this->factory->cart(null, ['currency' => $currency]);
    }

    protected function tearDown(): void
    {
        $this->getService(SaleUnitContext::class)->reset();
        $this->getService(SaleUnitActivityChecker::class)->reset();
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testAnAdditionTheUnitStockCoversIsAccepted(): void
    {
        $this->sellFromTheUnit(unitStock: 2.0);

        $this->add(2);

        self::assertSame(2.0, (float) $this->line()?->getQuantity());
    }

    public function testAnAdditionBeyondTheUnitStockIsRefusedThoughTheNationalStockCoversIt(): void
    {
        $this->sellFromTheUnit(unitStock: 2.0);

        $this->expectException(NotEnoughStockException::class);

        $this->add(3);
    }

    public function testAppendingBeyondTheUnitStockIsRefused(): void
    {
        $this->sellFromTheUnit(unitStock: 2.0);
        $this->add(2);

        try {
            $this->add(1);
            self::fail('Appending past the unit stock must be refused.');
        } catch (NotEnoughStockException) {
        }

        self::assertSame(2.0, (float) $this->line()?->getQuantity(), 'The line keeps the quantity it had.');
    }

    public function testANewQuantityBeyondTheUnitStockIsRefused(): void
    {
        $this->sellFromTheUnit(unitStock: 2.0);
        $this->add(1);

        $this->expectException(NotEnoughStockException::class);

        $this->update($this->line(), 3);
    }

    public function testANewQuantityTheUnitHoldsIsAcceptedThoughTheNationalCounterIsShort(): void
    {
        $this->productSaleElements->setQuantity(0)->save($this->getPropelConnection());
        $this->sellFromTheUnit(unitStock: 5.0);
        $this->add(1);

        $this->update($this->line(), 4);

        self::assertSame(4.0, (float) $this->line()?->getQuantity());
    }

    public function testAShopThatResolvedNoUnitIsJudgedOnTheNationalCounter(): void
    {
        $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, 2.0);

        $this->add(5);

        self::assertSame(5.0, (float) $this->line()?->getQuantity(), 'With no unit resolved, the unit row is nobody\'s stock.');
    }

    private function sellFromTheUnit(float $unitStock): void
    {
        $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, $unitStock);
        $this->getService(SaleUnitContext::class)->setSaleUnit($this->saleUnit);
    }

    private function add(int $quantity): void
    {
        $event = new CartEvent($this->cart);
        $event
            ->setProductId($this->product->getId())
            ->setProductSaleElementsId($this->productSaleElements->getId())
            ->setQuantity($quantity)
            ->setNewness(true)
            ->setAppend(true);

        $this->dispatch($event, TheliaEvents::CART_ADDITEM);
    }

    private function update(?CartItem $line, int $quantity): void
    {
        self::assertInstanceOf(CartItem::class, $line);

        $event = new CartEvent($this->cart);
        $event->setCartItemId($line->getId())->setQuantity($quantity);

        $this->dispatch($event, TheliaEvents::CART_UPDATEITEM);
    }

    private function line(): ?CartItem
    {
        return CartItemQuery::create()
            ->filterByCartId($this->cart->getId())
            ->filterByProductSaleElementsId($this->productSaleElements->getId())
            ->findOne($this->getPropelConnection());
    }
}
