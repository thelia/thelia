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

namespace Thelia\Tests\Integration\Domain\Catalog\Stock;

use Propel\Runtime\Propel;
use Thelia\Domain\Catalog\Stock\SellableStock;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Catalog\Stock\VisitorSellableStock;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * A visitor of a sale unit that keeps its own stock, with no cart yet, is shown the own stock of
 * that unit as physical stock: the holds of the other carts of the same unit on that stock are
 * taken from it, as they would be once the visitor has a cart of that unit.
 */
final class VisitorWithoutCartOfASaleUnitTest extends ActionIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'checkout');
        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '15');
        ConfigQuery::resetCache();
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn(Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME));
    }

    protected function tearDown(): void
    {
        $this->getService(SaleUnitContext::class)->setSaleUnit(null);
        ConfigQuery::resetCache();
        $this->getService(TestCallerTransaction::class)->reset();
        parent::tearDown();
    }

    public function testTheLastOwnUnitHeldByAnotherCartOfTheSameSaleUnitIsNotOfferedToAVisitorWithoutCart(): void
    {
        $connection = Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME);
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $productSaleElements = $product->getDefaultSaleElements();
        $productSaleElements->setQuantity(5)->save($connection);

        $saleUnit = $this->factory->saleUnit();
        $this->factory->saleUnitProductSaleElements($saleUnit, $productSaleElements, 1.0);

        // Another buyer of the same sale unit holds the only unit the unit keeps.
        $holdingCart = $this->factory->cart(null, ['currency' => $this->factory->currency()]);
        $holdingCart->setSaleUnitId($saleUnit->getId())->save($connection);
        $this->factory->cartItem($holdingCart, $product, null, ['quantity' => 1.0]);
        $this->getService(CheckoutFacade::class)->holdStock($holdingCart);

        $fresh = ProductSaleElementsQuery::create()->findPk($productSaleElements->getId(), $connection);

        // The same visitor once it has a cart of that unit: nothing left.
        $visitorCart = $this->factory->cart(null, ['currency' => $this->factory->currency()]);
        $visitorCart->setSaleUnitId($saleUnit->getId())->save($connection);
        self::assertSame(0.0, $this->getService(SellableStock::class)->forPse($fresh, 1.0, (int) $saleUnit->getId(), (int) $visitorCart->getId()), 'Precondition: a cart of the unit is offered nothing.');

        // The visitor browses the catalogue of that sale unit, with no cart yet.
        $this->getService(SaleUnitContext::class)->setSaleUnit($saleUnit);

        self::assertSame(
            0.0,
            $this->getService(VisitorSellableStock::class)->forPse($fresh),
            'The only own unit of the sale unit is held by another cart of that unit: a visitor of the unit without a cart must be offered nothing, as its cart would be.',
        );
    }
}
