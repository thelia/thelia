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

use Thelia\Domain\Cart\Bundle\CartLineAvailability;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Cart\DTO\CartItemUpdateQuantityDTO;
use Thelia\Domain\Cart\Exception\NotEnoughStockException;
use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Stock\OutOfStockPolicy;
use Thelia\Domain\Catalog\Stock\SellableStock;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;
use Thelia\Tests\Support\Order\RecordsStatements;

/**
 * The cart holds what a sale element may sell, not only what it has in stock: the stock plus what
 * its sale mode allows below zero. The silent cap of an addition to a line and the refusal of a
 * change of quantity keep their behaviour, with that number as the limit.
 */
final class SellableCartStockTest extends ActionIntegrationTestCase
{
    use BuildsBundles;
    use RecordsStatements;

    private Cart $cart;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::resetCache();
        $this->cart = $this->factory->cart(null, ['currency' => $this->factory->currency()]);
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTenOnALimitOfTenFromZeroAreAcceptedAndAnotherLineOfOneIsRefused(): void
    {
        $product = $this->product(0, OutOfStockPolicy::Limited, 10.0);

        $this->add($product, 10);

        try {
            $this->add($product, 1, append: false);
            self::fail('The eleventh unit goes beyond the limit.');
        } catch (NotEnoughStockException) {
        }

        self::assertSame(1, $this->lineCount());
    }

    public function testANewLineBeyondTheLimitIsRefusedWholeAndNothingIsWritten(): void
    {
        $product = $this->product(0, OutOfStockPolicy::Limited, 10.0);

        try {
            $this->add($product, 11);
            self::fail('Eleven on a limit of ten is refused.');
        } catch (NotEnoughStockException) {
        }

        self::assertSame(0, $this->lineCount());
    }

    public function testTheModeThatRefusesStaysAsItWas(): void
    {
        $product = $this->product(0, OutOfStockPolicy::Refuse, 10.0);

        $this->expectException(NotEnoughStockException::class);

        $this->add($product, 1);
    }

    public function testAnUnlimitedModeTakesWhateverIsAsked(): void
    {
        $product = $this->product(0, OutOfStockPolicy::Unlimited, null);

        $this->add($product, 5000);

        self::assertSame(5000.0, $this->quantityOfTheLine());
    }

    public function testAnAdditionToALineIsCappedInSilenceAtWhatMayBeSoldAndNotAtTheStock(): void
    {
        $product = $this->product(3, OutOfStockPolicy::Limited, 10.0);

        $this->add($product, 8);
        $this->add($product, 6);

        self::assertSame(8.0, $this->quantityOfTheLine(), 'Thirteen at most: eight and six make fourteen, the line keeps what it had.');
    }

    public function testAnAdditionToALineWithinWhatMayBeSoldIsTaken(): void
    {
        $product = $this->product(3, OutOfStockPolicy::Limited, 10.0);

        $this->add($product, 8);
        $this->add($product, 5);

        self::assertSame(13.0, $this->quantityOfTheLine());
    }

    public function testAChangeOfQuantityBeyondWhatMayBeSoldIsRefusedAndWithinItAccepted(): void
    {
        $product = $this->product(0, OutOfStockPolicy::Limited, 10.0);
        $this->add($product, 5);
        $line = CartItemQuery::create()->filterByCartId($this->cart->getId())->findOne();

        $this->getService(CartFacade::class)->updateItemQuantity(new CartItemUpdateQuantityDTO($this->cart, $line->getId(), 10));
        self::assertSame(10.0, $this->quantityOfTheLine());

        $this->expectException(NotEnoughStockException::class);
        $this->getService(CartFacade::class)->updateItemQuantity(new CartItemUpdateQuantityDTO($this->cart, $line->getId(), 11));
    }

    public function testAShopThatDoesNotCheckTheStockAcceptsAnything(): void
    {
        ConfigQuery::write('check-available-stock', '0');
        ConfigQuery::resetCache();
        $product = $this->product(0, OutOfStockPolicy::Refuse, null);

        $this->add($product, 50);

        self::assertSame(50.0, $this->quantityOfTheLine());
    }

    public function testTheFacadeOfTheLineSaysWhatMayBeSold(): void
    {
        $availability = $this->getService(CartLineAvailability::class);

        self::assertSame(13.0, $availability->forLine($this->product(3, OutOfStockPolicy::Limited, 10.0)->getDefaultSaleElements()));
        self::assertSame(3.0, $availability->forLine($this->product(3, OutOfStockPolicy::Refuse, 10.0)->getDefaultSaleElements()));
        self::assertSame((float) CartLineAvailability::UNLIMITED, $availability->forLine($this->product(0, OutOfStockPolicy::Unlimited, null)->getDefaultSaleElements()));
    }

    /** The stock of the sale unit the visitor buys from is read once for an addition, not once per reader. */
    public function testAnAdditionReadsTheOwnStockOfTheSaleUnitOnce(): void
    {
        $product = $this->product(5, OutOfStockPolicy::Refuse, null);
        $unit = $this->factory->saleUnit();
        $this->factory->saleUnitProductSaleElements($unit, $product->getDefaultSaleElements(), 3.0);
        $this->cart->setSaleUnitId($unit->getId())->save($this->getPropelConnection());
        $context = $this->getService(SaleUnitContext::class);
        $context->setSaleUnit($unit);

        try {
            $statements = $this->statementsDuring(fn () => $this->add($product, 2));
        } finally {
            $context->setSaleUnit(null);
        }

        self::assertSame(2.0, $this->quantityOfTheLine());
        self::assertCount(1, array_filter($statements, static fn (string $statement): bool => str_starts_with($statement, 'SELECT') && str_contains($statement, 'FROM `sale_unit_product_sale_elements`')));
    }

    /**
     * A shop that does not check its stock supplies a pack whatever its components hold, as any other
     * line: nothing runs out, nothing is refused.
     */
    public function testAShopThatDoesNotCheckTheStockSuppliesAPackWithoutLimit(): void
    {
        ConfigQuery::write('check-available-stock', '0');
        ConfigQuery::resetCache();
        $example = $this->exampleBundle($this->factory, $this->getService(BundleCompositionService::class));
        $example['helmetProduct']->getDefaultSaleElements()->setQuantity(0)->save($this->getPropelConnection());
        $composition = BundleComposition::fromArray([
            ['bundleItemId' => (int) $example['bike']->getId(), 'quantity' => '1'],
            ['bundleItemId' => (int) $example['helmet']->getId(), 'quantity' => '2'],
            ['bundleItemId' => (int) $example['lock']->getId(), 'quantity' => '2'],
        ]);
        $availability = $this->getService(CartLineAvailability::class);
        $pack = $example['bundle']->getDefaultSaleElements();

        self::assertSame((float) CartLineAvailability::UNLIMITED, $availability->forLine($pack, $composition));
        self::assertSame(['line' => (float) CartLineAvailability::UNLIMITED], $availability->forBundleLines(['line' => [$pack, $composition]]));
    }

    /** The sale elements a caller read already, with their product, are not read again for the cart. */
    public function testTheStockOfACartTakesTheSaleElementsAlreadyRead(): void
    {
        $product = $this->product(5, OutOfStockPolicy::Refuse, null);
        $this->add($product, 2);
        $saleElements = ProductSaleElementsQuery::create()->joinWithProduct()->findPk($product->getDefaultSaleElements()->getId(), $this->getPropelConnection());
        $sellableStock = $this->getService(SellableStock::class);
        $cart = CartQuery::create()->findPk($this->cart->getId(), $this->getPropelConnection());
        $cart->getCartItems();

        $answer = null;
        $statements = $this->statementsDuring(static function () use ($sellableStock, $cart, $saleElements, &$answer): void {
            $answer = $sellableStock->forCart($cart, [(int) $saleElements->getId() => $saleElements]);
        });

        self::assertSame([['available' => 5.0, 'preorder' => false]], array_values($answer ?? []));
        self::assertSame([], array_values(array_filter($statements, static fn (string $statement): bool => str_contains($statement, 'FROM `product_sale_elements`'))));
    }

    private function product(int $quantity, OutOfStockPolicy $policy, ?float $limit): Product
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $product->getDefaultSaleElements()
            ->setQuantity($quantity)
            ->setOutOfStockPolicy($policy->value)
            ->setPreorderLimit($limit)
            ->save($this->getPropelConnection());

        return $product;
    }

    private function add(Product $product, int $quantity, bool $append = true): void
    {
        /** @var ProductSaleElements $saleElements */
        $saleElements = $product->getDefaultSaleElements();

        $this->getService(CartFacade::class)->addItem(new CartItemAddDTO(
            cart: $this->cart,
            productId: $product->getId(),
            productSaleElementId: $saleElements->getId(),
            quantity: $quantity,
            append: $append,
        ));
    }

    private function lineCount(): int
    {
        return CartItemQuery::create()->filterByCartId($this->cart->getId())->count($this->getPropelConnection());
    }

    private function quantityOfTheLine(): float
    {
        return (float) CartItemQuery::create()->filterByCartId($this->cart->getId())->findOne($this->getPropelConnection())->getQuantity();
    }
}
