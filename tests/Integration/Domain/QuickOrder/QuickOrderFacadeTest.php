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

namespace Thelia\Tests\Integration\Domain\QuickOrder;

use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\CustomerList\Exception\PurchaseListNotFoundException;
use Thelia\Domain\CustomerList\PurchaseListFacade;
use Thelia\Domain\QuickOrder\DTO\QuickOrderLine;
use Thelia\Domain\QuickOrder\Exception\QuickOrderCartNotFoundException;
use Thelia\Domain\QuickOrder\Exception\QuickOrderRateLimitedException;
use Thelia\Domain\QuickOrder\QuickOrderFacade;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class QuickOrderFacadeTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private QuickOrderFacade $facade;

    private Currency $currency;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->facade = $this->getService(QuickOrderFacade::class);
        $this->currency = $this->factory->currency();
        $this->customer = $this->factory->customer($this->factory->customerTitle());
    }

    public function testResolvingWritesNothingToTheCart(): void
    {
        $cart = $this->factory->cart($this->customer, ['currency' => $this->currency]);
        $saleElements = $this->saleElements();

        $table = $this->facade->resolve($this->customer, self::lines([(string) $saleElements->getRef() => 2]), $this->currency);

        self::assertFalse($table->lines[0]->added);
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testOnlyTheResolvedLinesReachTheCart(): void
    {
        $cart = $this->factory->cart($this->customer, ['currency' => $this->currency]);
        $first = $this->saleElements();
        $second = $this->saleElements();

        $table = $this->facade->addToCart($this->customer, $cart, self::lines([
            (string) $first->getRef() => 2,
            'NOT-IN-THE-CATALOG' => 5,
            (string) $second->getRef() => 1,
        ]));

        self::assertSame([true, false, true], array_map(static fn (QuickOrderLine $line): bool => $line->added, $table->lines));
        self::assertSame(
            [[(int) $first->getId(), 2.0], [(int) $second->getId(), 1.0]],
            $this->cartLines($cart),
        );
    }

    public function testAReferenceAlreadyInTheCartAddsToItsQuantity(): void
    {
        $cart = $this->factory->cart($this->customer, ['currency' => $this->currency]);
        $saleElements = $this->saleElements();
        $lines = self::lines([(string) $saleElements->getRef() => 2]);

        $this->facade->addToCart($this->customer, $cart, $lines);
        $this->facade->addToCart($this->customer, $cart, $lines);

        self::assertSame([[(int) $saleElements->getId(), 4.0]], $this->cartLines($cart));
    }

    public function testTheCartOfAnotherCustomerIsRefused(): void
    {
        $cart = $this->factory->cart($this->factory->customer($this->factory->customerTitle()), ['currency' => $this->currency]);

        $this->expectException(QuickOrderCartNotFoundException::class);

        $this->facade->addToCart($this->customer, $cart, self::lines([(string) $this->saleElements()->getRef() => 1]));
    }

    public function testALoadedListAnswersInTheFormatOfTheResolution(): void
    {
        $saleElements = $this->saleElements();
        $list = $this->getService(PurchaseListFacade::class)->create($this->customer, 'Restock', self::lines([(string) $saleElements->getRef() => 3]));

        $table = $this->facade->resolvePurchaseList($this->customer, (int) $list->getId(), $this->currency);

        self::assertCount(1, $table->lines);
        self::assertSame((int) $saleElements->getId(), $table->lines[0]->productSaleElementsId);
        self::assertSame(3, $table->lines[0]->quantity);
    }

    public function testTheListOfAnotherAccountIsNotLoaded(): void
    {
        $list = $this->getService(PurchaseListFacade::class)->create($this->factory->customer($this->factory->customerTitle()), 'Not yours');

        $this->expectException(PurchaseListNotFoundException::class);

        $this->facade->resolvePurchaseList($this->customer, (int) $list->getId(), $this->currency);
    }

    public function testEveryEntryPointSpendsTheSameBudget(): void
    {
        $cart = $this->factory->cart($this->customer, ['currency' => $this->currency]);
        $lines = self::lines(['ANY' => 1]);

        for ($n = 1; $n <= 10; ++$n) {
            $this->facade->resolve($this->customer, $lines, $this->currency);
            $this->facade->addToCart($this->customer, $cart, $lines);
            $this->spendOnAForeignList();
        }

        $this->expectException(QuickOrderRateLimitedException::class);

        $this->facade->resolve($this->customer, $lines, $this->currency);
    }

    public function testAListOfAnotherAccountCostsTheSameAsOneOfOnesOwn(): void
    {
        for ($n = 1; $n <= 30; ++$n) {
            $this->spendOnAForeignList();
        }

        $this->expectException(QuickOrderRateLimitedException::class);

        $this->facade->resolve($this->customer, self::lines(['ANY' => 1]), $this->currency);
    }

    public function testALineTheCartTurnsDownIsReportedRefusedRatherThanAdded(): void
    {
        $cart = $this->factory->cart($this->customer, ['currency' => $this->currency]);
        $saleElements = $this->saleElements(5);
        $this->factory->cartItem($cart, $saleElements->getProduct(), $saleElements, ['quantity' => 4.0]);

        $line = $this->facade->addToCart($this->customer, $cart, self::lines([(string) $saleElements->getRef() => 3]))->lines[0];

        self::assertSame(['quantity_refused', false, 1.0], [$line->status->value, $line->added, $line->availableQuantity]);
        self::assertSame([[(int) $saleElements->getId(), 4.0]], $this->cartLines($cart));
    }

    public function testTwoLinesOfOneSaleElementShareItsStock(): void
    {
        $cart = $this->factory->cart($this->customer, ['currency' => $this->currency]);
        $saleElements = $this->saleElements(5);
        $reference = (string) $saleElements->getRef();

        $table = $this->facade->addToCart($this->customer, $cart, new ReferenceQuantityLines([
            new ReferenceQuantity($reference, 3),
            new ReferenceQuantity(mb_strtolower($reference), 3),
        ]));

        self::assertSame(
            [['resolved', true, null], ['quantity_refused', false, 2.0]],
            array_map(static fn (QuickOrderLine $line): array => [$line->status->value, $line->added, $line->availableQuantity], $table->lines),
        );
        self::assertSame([[(int) $saleElements->getId(), 3.0]], $this->cartLines($cart));
    }

    private function spendOnAForeignList(): void
    {
        try {
            $this->facade->resolvePurchaseList($this->customer, \PHP_INT_MAX, $this->currency);
        } catch (PurchaseListNotFoundException) {
        }
    }

    /**
     * @param array<string, int> $quantities
     */
    private static function lines(array $quantities): ReferenceQuantityLines
    {
        $lines = [];

        foreach ($quantities as $reference => $quantity) {
            $lines[] = new ReferenceQuantity((string) $reference, $quantity);
        }

        return new ReferenceQuantityLines($lines);
    }

    /**
     * @return list<array{int, float}>
     */
    private function cartLines(Cart $cart): array
    {
        $lines = [];

        foreach (CartItemQuery::create()->filterByCartId($cart->getId())->orderById()->find() as $item) {
            $lines[] = [(int) $item->getProductSaleElementsId(), (float) $item->getQuantity()];
        }

        return $lines;
    }

    private function saleElements(int $stock = 50): ProductSaleElements
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->currency, ['baseQuantity' => $stock]);

        return $product->getProductSaleElementss()->getFirst()
            ?? throw new \LogicException('The product has no sale element.');
    }
}
