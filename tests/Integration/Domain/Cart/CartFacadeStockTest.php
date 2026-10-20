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

use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Cart\Exception\NotEnoughStockException;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A line added without the cart form (a module, a component, a quick order going
 * through CartFacade) is held to the same stock rule as the form: refused beyond
 * the stock, accepted when the shop does not check the stock or the product is
 * virtual.
 */
final class CartFacadeStockTest extends ActionIntegrationTestCase
{
    private Product $product;

    private ProductSaleElements $productSaleElements;

    private Cart $cart;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check-available-stock', '1');

        $currency = $this->factory->currency();
        $this->product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency);
        $this->productSaleElements = $this->product->getDefaultSaleElements();
        $this->productSaleElements->setQuantity(2)->save($this->getPropelConnection());
        $this->cart = $this->factory->cart(null, ['currency' => $currency]);
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testANewLineBeyondTheStockIsRefused(): void
    {
        try {
            $this->add(5);
            self::fail('A new line beyond the stock must be refused.');
        } catch (NotEnoughStockException) {
        }

        self::assertSame(0, $this->lineCount(), 'No line is written.');
    }

    public function testANewLineTheStockCoversIsAccepted(): void
    {
        $this->add(2);

        self::assertSame(1, $this->lineCount());
    }

    public function testANewLineBeyondTheStockIsAcceptedWhenTheShopDoesNotCheckTheStock(): void
    {
        ConfigQuery::write('check-available-stock', '0');

        $this->add(5);

        self::assertSame(1, $this->lineCount());
    }

    public function testANewLineOfAVirtualProductIsAcceptedBeyondTheStock(): void
    {
        $this->product->setVirtual(1)->save($this->getPropelConnection());

        $this->add(5);

        self::assertSame(1, $this->lineCount());
    }

    public function testASecondLineOfTheSameSaleElementCountsTheUnitsOfTheFirst(): void
    {
        $this->add(2);

        try {
            $this->add(1, append: false);
            self::fail('A second line must not take the sale element beyond its stock.');
        } catch (NotEnoughStockException) {
        }

        self::assertSame(1, $this->lineCount(), 'Only the first line is written.');
    }

    public function testASecondLineWithinTheRemainingStockIsAccepted(): void
    {
        $this->add(1);
        $this->add(1, append: false);

        self::assertSame(2, $this->lineCount());
    }

    private function add(int $quantity, bool $append = true): void
    {
        $this->getService(CartFacade::class)->addItem(new CartItemAddDTO(
            cart: $this->cart,
            productId: $this->product->getId(),
            productSaleElementId: $this->productSaleElements->getId(),
            quantity: $quantity,
            append: $append,
        ));
    }

    private function lineCount(): int
    {
        return CartItemQuery::create()
            ->filterByCartId($this->cart->getId())
            ->count($this->getPropelConnection());
    }
}
