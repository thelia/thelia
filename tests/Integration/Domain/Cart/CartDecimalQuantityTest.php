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

namespace Thelia\Tests\Integration\Domain\Cart;

use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Cart\DTO\CartItemUpdateQuantityDTO;
use Thelia\Domain\Catalog\Quantity\QuantityRule;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A combination sold by measure reaches the cart with its decimals: the DTOs, the
 * cart event and the line carry 1.25 as 1.25, where the event used to cut it to 1.
 */
final class CartDecimalQuantityTest extends ActionIntegrationTestCase
{
    private Product $product;

    private ProductSaleElements $productSaleElements;

    private Cart $cart;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = $this->factory->currency();
        $this->product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency, ['baseQuantity' => 100]);
        $this->productSaleElements = $this->product->getDefaultSaleElements();
        (new QuantityRule(minQuantity: 0.25, step: 0.25, decimals: 2))->applyTo($this->productSaleElements);
        $this->productSaleElements->save($this->getPropelConnection());
        $this->cart = $this->factory->cart(null, ['currency' => $currency]);
    }

    public function testTheCartEventKeepsTheDecimals(): void
    {
        self::assertSame(1.25, (new CartEvent($this->cart))->setQuantity(1.25)->getQuantity());
        self::assertSame(3.0, (new CartEvent($this->cart))->setQuantity(3)->getQuantity());
    }

    public function testALineSoldByMeasureIsAddedWithItsDecimals(): void
    {
        $line = $this->facade()->addItem(new CartItemAddDTO(
            cart: $this->cart,
            productId: $this->product->getId(),
            productSaleElementId: $this->productSaleElements->getId(),
            quantity: 1.25,
        ));

        self::assertSame(1.25, $this->storedQuantity((int) $line->getId()));
    }

    public function testALineSoldByMeasureIsChangedWithItsDecimals(): void
    {
        $line = $this->factory->cartItem($this->cart, $this->product, $this->productSaleElements, ['quantity' => 1]);

        $this->facade()->updateItemQuantity(new CartItemUpdateQuantityDTO($this->cart, (int) $line->getId(), 2.75));

        self::assertSame(2.75, $this->storedQuantity((int) $line->getId()));
    }

    private function facade(): CartFacade
    {
        return $this->getService(CartFacade::class);
    }

    private function storedQuantity(int $lineId): ?float
    {
        CartItemTableMap::clearInstancePool();
        $quantity = CartItemQuery::create()->findPk($lineId, $this->getPropelConnection())?->getQuantity();

        return null === $quantity ? null : round($quantity, 6);
    }
}
