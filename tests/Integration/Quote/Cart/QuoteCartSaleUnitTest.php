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

namespace Thelia\Tests\Integration\Quote\Cart;

use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\SaleUnit;

/**
 * A buyer who reaches another sale unit has the lines it cannot sell taken out of their cart. The
 * lines of a quote are not: the shop agreed to them, and what the unit cannot supply is judged
 * when the order is placed.
 */
final class QuoteCartSaleUnitTest extends QuoteCartTestCase
{
    private SaleUnit $first;

    private SaleUnit $second;

    private bool $wasAdminEnv = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wasAdminEnv = Request::$isAdminEnv;
        Request::$isAdminEnv = false;
        ConfigQuery::write('check-available-stock', '1');
        $this->first = $this->factory->saleUnit();
        $this->second = $this->factory->saleUnit();
    }

    protected function tearDown(): void
    {
        $this->getService(SaleUnitContext::class)->reset();
        $this->getService(SaleUnitActivityChecker::class)->reset();
        ConfigQuery::resetCache();
        Request::$isAdminEnv = $this->wasAdminEnv;

        parent::tearDown();
    }

    public function testAUnitThatCannotSellTheLineOfAQuoteLeavesItInTheCart(): void
    {
        $quoteCart = $this->quoteCart();
        $quoteCart->setSaleUnitId($this->first->getId())->save($this->getPropelConnection());
        $this->factory->saleUnitProductSaleElements($this->second, $this->quoteLine->getProductSaleElements(), 0.0);

        $this->getService(SaleUnitContext::class)->setSaleUnit($this->second);
        $this->dispatch(new CartEvent($this->reloaded($quoteCart)), TheliaEvents::CART_FOLLOW_SALE_UNIT);

        CartItemTableMap::clearInstancePool();
        $line = CartItemQuery::create()->filterByCartId($quoteCart->getId())->findOne();
        self::assertNotNull($line);
        self::assertSame(12.0, (float) $line->getQuantity());
        self::assertSame(90.0, round((float) $line->getPrice(), 6));
    }

    public function testTheSameUnitTakesTheLineOutOfAnOrdinaryCart(): void
    {
        $ordinaryCart = $this->ordinaryCart();
        $ordinaryCart->setSaleUnitId($this->first->getId())->save($this->getPropelConnection());
        $this->factory->saleUnitProductSaleElements($this->second, $this->lineOf($ordinaryCart)->getProductSaleElements(), 0.0);

        $this->getService(SaleUnitContext::class)->setSaleUnit($this->second);
        $this->dispatch(new CartEvent($this->reloaded($ordinaryCart)), TheliaEvents::CART_FOLLOW_SALE_UNIT);

        CartItemTableMap::clearInstancePool();
        self::assertSame(0, CartItemQuery::create()->filterByCartId($ordinaryCart->getId())->count());
    }
}
