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
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\AvailabilityFilter;
use Thelia\Command\StockReservationPurgeCommand;
use Thelia\Core\Event\Cart\CartDuplicationEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Bundle\CartLineAvailability;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Cart\Exception\NotEnoughStockException;
use Thelia\Domain\Cart\Service\CartLinePricer;
use Thelia\Domain\Catalog\Bundle\BundleComponentReader;
use Thelia\Domain\Catalog\Stock\Exception\StockReservationRefusedException;
use Thelia\Domain\Catalog\Stock\OutOfStockPolicy;
use Thelia\Domain\Catalog\Stock\SellableStock;
use Thelia\Domain\Catalog\Stock\StockReservationPurger;
use Thelia\Domain\Catalog\Stock\StockReservationService;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Order\Exception\StockShortageException;
use Thelia\Domain\Order\Service\StockDecrementer;
use Thelia\Domain\SaleUnit\Stock\SaleUnitStockResolver;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\PlacesBundleOrders;
use Thelia\Tests\Support\Order\RecordsStatements;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * The stock a cart holds while its buyer goes through the checkout (or from the cart on), and
 * what that leaves to every other cart: physical 1 held by A leaves 0 to B and 1 to A; once
 * the hold runs out, B gets the unit back without any purge.
 */
final class StockReservationTest extends ActionIntegrationTestCase
{
    use PlacesBundleOrders;
    use RecordsStatements;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        $this->reserve(true);

        // The transaction this test wraps itself in is not one of a caller: a hold runs in it.
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn($this->connection());
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher->removeListener($eventName, $listener);
        }

        ConfigQuery::resetCache();
        $this->getService(TestCallerTransaction::class)->reset();

        parent::tearDown();
    }

    public function testTheLastUnitHeldByOneCartLeavesNothingToAnotherAndEverythingToItself(): void
    {
        $product = $this->product(1);
        [$cartA, $lineA] = $this->cartWith($product, 1);
        $cartB = $this->factory->cart(null, ['currency' => $this->factory->currency()]);

        $this->checkout()->holdStock($cartA);

        self::assertTrue($this->isHeld($lineA));
        self::assertSame(0.0, $this->sellableFor($product, $cartB));
        self::assertSame(1.0, $this->sellableFor($product, $cartA));
        self::assertSame(0.0, $this->sellableFor($product, null), 'A reader with no cart counts every hold.');
    }

    /**
     * A step of the checkout called inside a transaction of its caller would go on with nothing held:
     * it is a defect, raised, and nothing is held.
     */
    public function testAHoldForTheCheckoutInsideATransactionOfTheCallerIsRefused(): void
    {
        $product = $this->product(1);
        [$cart, $line] = $this->cartWith($product, 1);
        $this->connection()->beginTransaction();

        try {
            $this->checkout()->holdStock($cart);
            self::fail('A hold for the checkout inside a transaction of the caller is a defect.');
        } catch (\LogicException $defect) {
            self::assertStringContainsString('inside a transaction of the caller', $defect->getMessage());
        } finally {
            $this->connection()->commit();
        }

        self::assertFalse($this->isHeld($line));
    }

    /** A change of the cart inside a transaction of its caller holds nothing, and fails nothing. */
    public function testAHoldAfterACartChangeInsideATransactionOfTheCallerHoldsNothing(): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'cart');
        ConfigQuery::resetCache();
        $product = $this->product(1);
        [$cart, $line] = $this->cartWith($product, 1);
        $this->connection()->beginTransaction();

        try {
            $this->getService(StockReservationService::class)->holdAfterCartChange($cart);
        } finally {
            $this->connection()->commit();
        }

        self::assertFalse($this->isHeld($line));
    }

    public function testAHoldThatRanOutIsNotCountedWhetherOrNotThePurgeRan(): void
    {
        $product = $this->product(1);
        [$cartA, $lineA] = $this->cartWith($product, 1);
        $cartB = $this->factory->cart(null, ['currency' => $this->factory->currency()]);
        $this->checkout()->holdStock($cartA);

        $this->expire($lineA);

        self::assertFalse($this->isHeld($lineA));
        self::assertSame(1.0, $this->sellableFor($product, $cartB));
    }

    public function testTheCheckoutOfAnotherCartRefusesTheLineHeldElsewhereAndHoldsNothing(): void
    {
        $product = $this->product(1);
        [$cartB, $lineB] = $this->cartWith($product, 1);
        [$cartA] = $this->cartWith($product, 1);
        $this->checkout()->holdStock($cartA);

        try {
            $this->checkout()->holdStock($cartB);
            self::fail('The only unit is held by another cart.');
        } catch (StockReservationRefusedException $refusal) {
            self::assertSame($product->getRef(), $refusal->productReference);
            self::assertStringContainsString('is no longer available', $refusal->getMessage());
            self::assertInstanceOf(StockShortageException::class, $refusal, 'Every caller that answers a shortage answers it.');
        }

        self::assertFalse($this->isHeld($lineB));
    }

    public function testEachHoldPushesTheMomentBackByTheDurationOfTheShop(): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '30');
        ConfigQuery::resetCache();
        $product = $this->product(5);
        [$cart, $line] = $this->cartWith($product, 2);

        $this->checkout()->holdStock($cart);
        $this->connection()->exec('UPDATE cart_item SET reserved_until = NOW() + INTERVAL 1 MINUTE WHERE id = '.(int) $line->getId());
        $this->checkout()->holdStock($cart);

        self::assertGreaterThan(29 * 60 - 5, $this->secondsLeft($line));
        self::assertLessThanOrEqual(30 * 60, $this->secondsLeft($line));
    }

    public function testAStepOfTheCheckoutHoldsTheStock(): void
    {
        $product = $this->product(5);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $cart = $this->factory->cart($customer, ['currency' => $this->factory->currency()]);
        $line = $this->factory->cartItem($cart, $product, null, ['quantity' => 2.0]);
        $address = $this->factory->address($customer, $this->factory->country());

        $this->checkout()->selectDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: (int) $address->getId()));

        self::assertTrue($this->isHeld($line));
    }

    public function testFromTheCheckoutOnAnAdditionHoldsNothingAndFromTheCartOnItHolds(): void
    {
        $product = $this->product(5);
        $cart = $this->factory->cart(null, ['currency' => $this->factory->currency()]);

        $this->add($cart, $product, 2);
        self::assertFalse($this->isHeld($this->onlyLineOf($cart)), 'Checkout scope: the cart holds nothing yet.');

        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'cart');
        ConfigQuery::resetCache();
        $this->add($cart, $product, 1);

        self::assertTrue($this->isHeld($this->onlyLineOf($cart)), 'Cart scope: the addition holds the line.');
        self::assertSame(3.0, (float) $this->onlyLineOf($cart)->getQuantity());
    }

    /**
     * From the cart on, the copy of a cart made at sign-in holds what the cart it copies held:
     * physical 1 held by the visitor stays the buyer's once signed in, and no other cart gets it.
     */
    public function testFromTheCartOnTheCopyMadeAtSignInHoldsItsLines(): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'cart');
        ConfigQuery::resetCache();
        $product = $this->product(1);
        [$customer, $currency] = $this->aBuyer();
        $visitorCart = $this->factory->cart(null, ['currency' => $currency]);
        $this->add($visitorCart, $product, 1);
        self::assertTrue($this->isHeld($this->onlyLineOf($visitorCart)));

        // What Action\Cart does when the visitor signs in and the cart has to be priced again.
        $copy = $visitorCart->duplicate(
            null,
            $customer,
            $currency,
            $this->dispatcher,
            $this->getService(SaleUnitStockResolver::class),
            $this->getService(CartLineAvailability::class),
            $this->getService(CartLinePricer::class),
        );
        self::assertInstanceOf(Cart::class, $copy);
        $this->dispatcher->dispatch(new CartDuplicationEvent($copy, $visitorCart), TheliaEvents::CART_DUPLICATE);

        self::assertTrue($this->isHeld($this->onlyLineOf($copy)), 'The copy holds the unit the deleted cart held.');
        self::assertSame(0.0, $this->sellableFor($product, $this->factory->cart(null, ['currency' => $currency])));
    }

    public function testFromTheCartOnTheHoldOfAnotherCartCapsAnAdditionInSilence(): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'cart');
        ConfigQuery::resetCache();
        $product = $this->product(3);
        $cartA = $this->factory->cart(null, ['currency' => $this->factory->currency()]);
        $cartB = $this->factory->cart(null, ['currency' => $this->factory->currency()]);

        $this->add($cartB, $product, 1);
        $this->add($cartA, $product, 2);
        $this->add($cartB, $product, 2);

        self::assertSame(1.0, (float) $this->onlyLineOf($cartB)->getQuantity(), 'One left once A holds two: the line keeps what it had.');
        self::assertTrue($this->isHeld($this->onlyLineOf($cartB)));

        $this->expectException(NotEnoughStockException::class);
        $this->add($this->factory->cart(null, ['currency' => $this->factory->currency()]), $product, 1);
    }

    public function testAnOrderTurnsTheHoldIntoASaleTakenOnce(): void
    {
        $this->manageStockOnCreation();
        $product = $this->product(2);
        [$customer, $currency, $lang] = $this->aBuyer();
        $cartA = $this->aCart($customer, $currency);
        $lineA = $this->factory->cartItem($cartA, $product, null, ['quantity' => 1.0]);
        $cartB = $this->factory->cart(null, ['currency' => $currency]);
        $this->checkout()->holdStock($cartA);

        $this->placeOrder($cartA, $customer, $currency, $lang);

        self::assertSame(1.0, $this->physicalOf($product), 'Taken from the stock once.');
        self::assertFalse($this->isHeld($lineA), 'The cart ordered holds nothing any more.');
        self::assertSame(1.0, $this->sellableFor($product, $cartB), 'The unit sold is not held as well.');
    }

    public function testACancelledOrderGivesThePhysicalStockBackAndHoldsNothing(): void
    {
        $this->manageStockOnCreation();
        $product = $this->product(2);
        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $line = $this->factory->cartItem($cart, $product, null, ['quantity' => 2.0]);
        $this->checkout()->holdStock($cart);
        $order = $this->placeOrder($cart, $customer, $currency, $lang);
        self::assertSame(0.0, $this->physicalOf($product));

        $order->setCancelled($this->dispatcher);

        self::assertSame(2.0, $this->physicalOf($product));
        self::assertFalse($this->isHeld($line));
        self::assertSame(2.0, $this->sellableFor($product, null));
    }

    public function testAnOrderNeverTakesTheUnitAnotherCartHolds(): void
    {
        $this->manageStockOnCreation();
        $product = $this->product(1);
        [$customer, $currency, $lang] = $this->aBuyer();
        $cartB = $this->aCart($customer, $currency);
        $this->factory->cartItem($cartB, $product, null, ['quantity' => 1.0]);
        [$cartA] = $this->cartWith($product, 1);
        $this->checkout()->holdStock($cartA);

        try {
            $this->placeOrder($cartB, $customer, $currency, $lang);
            self::fail('The unit is held by another cart.');
        } catch (StockShortageException $shortage) {
            self::assertSame($product->getRef(), $shortage->productReference);
        }

        self::assertSame(1.0, $this->physicalOf($product));
    }

    /**
     * The guard of the decrement itself, the one that stands when two placements race past the
     * check made before the transaction.
     */
    public function testTheDecrementLeavesWhatOtherCartsHold(): void
    {
        $product = $this->product(1);
        $decrementer = $this->getService(StockDecrementer::class);

        try {
            $decrementer->decrement((int) $product->getDefaultSaleElements()->getId(), 1.0, true, false, $this->connection(), null, 1.0);
            self::fail('The only unit is held by another cart.');
        } catch (TheliaProcessException) {
        }

        self::assertSame(1.0, $this->physicalOf($product));

        $movement = $decrementer->decrement((int) $product->getDefaultSaleElements()->getId(), 1.0, true, false, $this->connection());
        self::assertSame(0.0, $movement?->newQuantity);
    }

    public function testAPreorderBeyondWhatOtherCartsHoldIsMarkedSo(): void
    {
        $product = $this->product(3, OutOfStockPolicy::Limited, 10.0);

        $movement = $this->getService(StockDecrementer::class)->decrement((int) $product->getDefaultSaleElements()->getId(), 2.0, true, false, $this->connection(), null, 3.0);

        self::assertTrue($movement?->preorder, 'The three units on the shelf are held by others: these two ship on restock.');
        self::assertSame(1.0, $movement->newQuantity);
    }

    /**
     * The stock is taken when the order is paid: by then another cart holds the unit, and the
     * payment is refused rather than selling it to two buyers.
     */
    /**
     * Physical 1, the stock taken at the payment: A places its order and has not paid yet. The
     * unit stays A's: B can neither hold it nor order it, and A pays without a refusal.
     */
    public function testAStockLeftForThePaymentStaysHeldUntilTheOrderIsPaid(): void
    {
        $this->manageStockOnCreation(false);
        $product = $this->product(1);
        [$customer, $currency, $lang] = $this->aBuyer();
        $cartA = $this->aCart($customer, $currency);
        $lineA = $this->factory->cartItem($cartA, $product, null, ['quantity' => 1.0]);
        $this->checkout()->holdStock($cartA);
        $order = $this->placeOrder($cartA, $customer, $currency, $lang);
        self::assertSame(1.0, $this->physicalOf($product), 'Nothing taken before the payment.');
        self::assertTrue($this->isHeld($lineA), 'The order left the stock to its payment: its cart still holds it.');

        $cartB = $this->aCart($customer, $currency);
        $lineB = $this->factory->cartItem($cartB, $product, null, ['quantity' => 1.0]);

        try {
            $this->checkout()->holdStock($cartB);
            self::fail('The only unit waits for the payment of another order.');
        } catch (StockReservationRefusedException) {
        }

        try {
            $this->placeOrder($cartB, $customer, $currency, $lang);
            self::fail('The only unit waits for the payment of another order.');
        } catch (StockShortageException $shortage) {
            self::assertSame($product->getRef(), $shortage->productReference);
        }

        self::assertFalse($this->isHeld($lineB));

        $this->dispatcher->dispatch((new OrderEvent($order))->setStatus((int) OrderStatusQuery::getPaidStatus()?->getId()), TheliaEvents::ORDER_UPDATE_STATUS);

        self::assertSame(0.0, $this->physicalOf($product), 'Taken at the payment.');
        self::assertFalse($this->isHeld($lineA), 'Sold: the cart holds it no more.');
    }

    public function testACancelledOrderWhoseStockWaitedForThePaymentReleasesItsCart(): void
    {
        $this->manageStockOnCreation(false);
        $product = $this->product(1);
        [$customer, $currency, $lang] = $this->aBuyer();
        $cartA = $this->aCart($customer, $currency);
        $lineA = $this->factory->cartItem($cartA, $product, null, ['quantity' => 1.0]);
        $this->checkout()->holdStock($cartA);
        $order = $this->placeOrder($cartA, $customer, $currency, $lang);

        $this->dispatcher->dispatch((new OrderEvent($order))->setStatus((int) OrderStatusQuery::getCancelledStatus()?->getId()), TheliaEvents::ORDER_UPDATE_STATUS);

        self::assertFalse($this->isHeld($lineA));
        self::assertSame(1.0, $this->physicalOf($product));
        [$cartB, $lineB] = $this->cartWith($product, 1);
        $this->checkout()->holdStock($cartB);
        self::assertTrue($this->isHeld($lineB), 'Given up: the unit is free again.');
    }

    /** The stock was taken when the order was placed: its payment takes nothing, and locks nothing. */
    public function testThePaymentOfAnOrderWhoseStockWasTakenAtPlacementLocksNoStockRow(): void
    {
        $this->manageStockOnCreation(true);
        $product = $this->product(2);
        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->factory->cartItem($cart, $product, null, ['quantity' => 1.0]);
        $order = $this->placeOrder($cart, $customer, $currency, $lang);

        $statements = $this->statementsDuring(fn () => $this->dispatcher->dispatch((new OrderEvent($order))->setStatus((int) OrderStatusQuery::getPaidStatus()?->getId()), TheliaEvents::ORDER_UPDATE_STATUS));

        self::assertSame(1.0, $this->physicalOf($product));
        self::assertSame([], array_values(array_filter($statements, static fn (string $statement): bool => str_contains($statement, 'FROM `product_sale_elements`') && str_contains($statement, 'FOR UPDATE'))));
    }

    /**
     * The unit another cart holds is never taken by a payment either: an order placed before the
     * hold, whose stock waits for its payment, is refused when it is paid.
     */
    public function testAPaymentNeverTakesTheUnitAnotherCartHolds(): void
    {
        $this->manageStockOnCreation(false);
        $product = $this->product(1);
        [$customer, $currency, $lang] = $this->aBuyer();
        $cartA = $this->aCart($customer, $currency);
        $this->factory->cartItem($cartA, $product, null, ['quantity' => 1.0]);
        // Placed without the checkout: the cart of the order holds nothing.
        $order = $this->placeOrder($cartA, $customer, $currency, $lang);

        [$cartB, $lineB] = $this->cartWith($product, 1);
        $this->checkout()->holdStock($cartB);
        self::assertTrue($this->isHeld($lineB));

        try {
            $this->dispatcher->dispatch((new OrderEvent($order))->setStatus((int) OrderStatusQuery::getPaidStatus()?->getId()), TheliaEvents::ORDER_UPDATE_STATUS);
            self::fail('The unit is held by another cart.');
        } catch (TheliaProcessException) {
        }

        self::assertSame(1.0, $this->physicalOf($product));
    }

    /**
     * Physical 3 that sells without limit, another cart holds the 3: an order of 2 whose stock is
     * taken at its payment is a preorder when it is paid, by the rule the placement follows.
     */
    public function testAPaymentBeyondWhatOtherCartsHoldIsAPreorder(): void
    {
        $this->manageStockOnCreation(false);
        $product = $this->product(3, OutOfStockPolicy::Unlimited);
        [$customer, $currency, $lang] = $this->aBuyer();
        $cartA = $this->aCart($customer, $currency);
        $this->factory->cartItem($cartA, $product, null, ['quantity' => 2.0]);
        $order = $this->placeOrder($cartA, $customer, $currency, $lang);
        [$cartB, $lineB] = $this->cartWith($product, 3);
        $this->checkout()->holdStock($cartB);
        self::assertTrue($this->isHeld($lineB));

        $this->dispatcher->dispatch((new OrderEvent($order))->setStatus((int) OrderStatusQuery::getPaidStatus()?->getId()), TheliaEvents::ORDER_UPDATE_STATUS);

        self::assertSame(1.0, $this->physicalOf($product));
        self::assertSame([1], array_map(
            static fn (\Thelia\Model\OrderProduct $line): int => (int) $line->getIsPreorder(),
            \Thelia\Model\OrderProductQuery::create()->filterByOrderId($order->getId())->find($this->connection())->getData(),
        ));
    }

    public function testTheAvailabilityOfTheCatalogueLeavesWhatTheCartsHold(): void
    {
        $product = $this->product(1);
        [$cart, $line] = $this->cartWith($product, 1);
        $filter = $this->getService(AvailabilityFilter::class);
        $read = fn (): string => $filter->getValue(ProductQuery::create()->findPk($product->getId(), $this->connection()), 'en_US')[0]->getTitle();
        self::assertSame('In stock', $read());

        $this->checkout()->holdStock($cart);
        self::assertSame('On order or out of stock', $read(), 'The only unit is held.');

        $this->expire($line);
        self::assertSame('In stock', $read());
    }

    public function testACartDeletedTakesItsHoldWithIt(): void
    {
        $product = $this->product(1);
        [$cartA] = $this->cartWith($product, 1);
        $this->checkout()->holdStock($cartA);
        self::assertSame(0.0, $this->sellableFor($product, null));

        $cartA->delete($this->connection());

        self::assertSame(1.0, $this->sellableFor($product, null));
    }

    public function testTheHoldOfACartIsCountedOnTheStockItsSaleUnitSellsFrom(): void
    {
        $product = $this->product(5);
        $productSaleElements = $product->getDefaultSaleElements();
        $ownStockUnit = $this->factory->saleUnit();
        $this->factory->saleUnitProductSaleElements($ownStockUnit, $productSaleElements, 1.0);
        $sharedStockUnit = $this->factory->saleUnit();

        [$ownStockCart] = $this->cartWith($product, 1, $ownStockUnit->getId());
        $this->checkout()->holdStock($ownStockCart);

        self::assertSame(0.0, $this->sellableFor($product, $this->factory->cart(null, ['currency' => $this->factory->currency()])->setSaleUnitId($ownStockUnit->getId()), 1.0), 'The own stock of the unit is held.');
        self::assertSame(5.0, $this->sellableFor($product, null), 'The national counter is untouched.');

        [$sharedStockCart] = $this->cartWith($product, 4, $sharedStockUnit->getId());
        $this->checkout()->holdStock($sharedStockCart);

        self::assertSame(1.0, $this->sellableFor($product, null), 'A unit without a stock of its own holds the national counter.');
    }

    public function testThePackComponentsLeaveWhatTheCartsHold(): void
    {
        $product = $this->product(3);
        [$cart] = $this->cartWith($product, 2);
        $this->checkout()->holdStock($cart);
        $productSaleElementsId = (int) $product->getDefaultSaleElements()->getId();

        self::assertSame(1.0, $this->getService(BundleComponentReader::class)->read([$productSaleElementsId])[$productSaleElementsId]->availableQuantity);
        self::assertSame(3.0, $this->getService(BundleComponentReader::class)->read([$productSaleElementsId], (int) $cart->getId())[$productSaleElementsId]->availableQuantity);
    }

    public function testVirtualProductsAreNeverHeld(): void
    {
        $product = $this->product(0);
        $product->setVirtual(1)->save($this->connection());
        [$cart, $line] = $this->cartWith($product, 3);

        $this->checkout()->holdStock($cart);

        self::assertFalse($this->isHeld($line));
    }

    public function testThePurgeClearsTheHoldsThatRanOutAndKeepsTheOthers(): void
    {
        $product = $this->product(10);
        [$cartA, $expired] = $this->cartWith($product, 1);
        [$cartB, $active] = $this->cartWith($product, 1);
        $this->checkout()->holdStock($cartA);
        $this->checkout()->holdStock($cartB);
        $this->expire($expired);

        $cleared = $this->getService(StockReservationPurger::class)->purgeExpired(1);

        self::assertGreaterThanOrEqual(1, $cleared);
        self::assertNull($this->storedReservedUntil($expired));
        self::assertTrue($this->isHeld($active));
    }

    public function testThePurgeCommandRunsThePurge(): void
    {
        $product = $this->product(10);
        [$cart, $line] = $this->cartWith($product, 1);
        $this->checkout()->holdStock($cart);
        $this->expire($line);

        $tester = new CommandTester($this->getService(StockReservationPurgeCommand::class));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('expired stock reservation(s) cleared', $tester->getDisplay());
        self::assertNull($this->storedReservedUntil($line));
        self::assertSame(2, $tester->execute(['--batch-size' => '0']), 'A batch of nothing is refused.');
    }

    /**
     * A shop that does not reserve reads and writes exactly what it did: nothing held, no lock,
     * no query, at the checkout and at the placement.
     */
    public function testAShopThatDoesNotReserveRunsNoQueryAndHoldsNothing(): void
    {
        $this->reserve(false);
        $this->manageStockOnCreation();
        $product = $this->product(2);
        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $line = $this->factory->cartItem($cart, $product, null, ['quantity' => 1.0]);
        $productSaleElements = $product->getDefaultSaleElements();

        self::assertSame([], $this->statementsDuring(fn () => $this->checkout()->holdStock($cart)));
        self::assertSame([], $this->statementsDuring(fn () => $this->getService(SellableStock::class)->forPse($productSaleElements, null, null, (int) $cart->getId())));

        $statements = $this->statementsDuring(fn () => $this->placeOrder($cart, $customer, $currency, $lang));

        // Propel lists the column in every read of a line: only a statement that filters on it or writes it is the feature.
        self::assertSame([], array_values(array_filter($statements, static fn (string $statement): bool => 1 === preg_match('/reserved_until`? (>|<|=|IS)|SET `reserved_until`/', $statement) || (str_contains($statement, 'FOR UPDATE') && str_contains($statement, 'product_sale_elements')))));
        self::assertFalse($this->isHeld($line));
        self::assertSame(1.0, $this->physicalOf($product));
    }

    /**
     * What reserving adds to a placement of a cart of one line: the lock of its stock row, the
     * reservations read by the check before the transaction and by the guard of the decrement,
     * and the lines of the cart (their ids, then their lock), the held ones released.
     */
    public function testWhatAShopThatReservesAddsToAPlacement(): void
    {
        $this->manageStockOnCreation();
        [$customer, $currency, $lang] = $this->aBuyer();

        $this->reserve(false);
        // A first placement warms the caches the two measured ones then share.
        $warmUp = $this->aCart($customer, $currency);
        $this->factory->cartItem($warmUp, $this->product(2), null, ['quantity' => 1.0]);
        $this->placeOrder($warmUp, $customer, $currency, $lang);

        $cartOff = $this->aCart($customer, $currency);
        $this->factory->cartItem($cartOff, $this->product(2), null, ['quantity' => 1.0]);
        $off = $this->statementsDuring(fn () => $this->placeOrder($cartOff, $customer, $currency, $lang));

        $this->reserve(true);
        $cartOn = $this->aCart($customer, $currency);
        $this->factory->cartItem($cartOn, $this->product(2), null, ['quantity' => 1.0]);
        $this->checkout()->holdStock($cartOn);
        $on = $this->statementsDuring(fn () => $this->placeOrder($cartOn, $customer, $currency, $lang));

        self::assertSame(\count($off) + 6, \count($on), 'Lock, two reads of the reservations, the lines of the cart found, locked and read, and the held ones released.');
    }

    /**
     * Inside the transaction of a placement, the reservations of the other carts are read once
     * for every line, right after the stock rows were locked: a cart of two lines reads them once.
     */
    public function testAPlacementReadsTheReservationsOnceAfterTheLock(): void
    {
        $this->manageStockOnCreation();
        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->factory->cartItem($cart, $this->product(2), null, ['quantity' => 1.0]);
        $this->factory->cartItem($cart, $this->product(2), null, ['quantity' => 1.0]);
        $this->checkout()->holdStock($cart);

        $statements = $this->statementsDuring(fn () => $this->placeOrder($cart, $customer, $currency, $lang));

        $lock = array_key_first(array_filter($statements, static fn (string $statement): bool => str_contains($statement, 'FROM `product_sale_elements`') && str_contains($statement, 'FOR UPDATE')));
        self::assertIsInt($lock);
        $readsAfterTheLock = array_filter(
            \array_slice($statements, $lock + 1),
            static fn (string $statement): bool => str_contains($statement, 'SUM(`cart_item`.`quantity`)'),
        );
        self::assertCount(1, $readsAfterTheLock, 'One read of the reservations for the two lines.');

        $readsBeforeTheLock = array_filter(
            \array_slice($statements, 0, $lock),
            static fn (string $statement): bool => str_contains($statement, 'SUM(`cart_item`.`quantity`)'),
        );
        self::assertCount(1, $readsBeforeTheLock, 'The check before the transaction reads them once too, whatever the number of lines.');
    }

    private function reserve(bool $enabled): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, $enabled ? '1' : '0');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'checkout');
        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '15');
        ConfigQuery::resetCache();
    }

    private function manageStockOnCreation(bool $onCreation = true): void
    {
        $eventName = TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque');
        $listener = static fn (ManageStockOnCreationEvent $event) => $event->setManageStock($onCreation);
        $this->dispatcher->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function checkout(): CheckoutFacade
    {
        return $this->getService(CheckoutFacade::class);
    }

    private function product(int $quantity, OutOfStockPolicy $policy = OutOfStockPolicy::Refuse, ?float $limit = null): Product
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $product->getDefaultSaleElements()
            ->setQuantity($quantity)
            ->setOutOfStockPolicy($policy->value)
            ->setPreorderLimit($limit)
            ->save($this->connection());

        return $product;
    }

    /**
     * @return array{0: Cart, 1: CartItem}
     */
    private function cartWith(Product $product, int $quantity, ?int $saleUnitId = null): array
    {
        $cart = $this->factory->cart(null, ['currency' => $this->factory->currency()]);

        if (null !== $saleUnitId) {
            $cart->setSaleUnitId($saleUnitId)->save($this->connection());
        }

        return [$cart, $this->factory->cartItem($cart, $product, null, ['quantity' => (float) $quantity])];
    }

    private function add(Cart $cart, Product $product, int $quantity): void
    {
        $this->getService(CartFacade::class)->addItem(new CartItemAddDTO(
            cart: $cart,
            productId: $product->getId(),
            productSaleElementId: $product->getDefaultSaleElements()->getId(),
            quantity: $quantity,
            append: true,
        ));
    }

    private function onlyLineOf(Cart $cart): CartItem
    {
        $lines = \Thelia\Model\CartItemQuery::create()->filterByCartId($cart->getId())->find($this->connection());
        self::assertCount(1, $lines);

        return $lines->getFirst();
    }

    private function sellableFor(Product $product, ?Cart $cart, ?float $physical = null): ?float
    {
        $cartId = (int) $cart?->getId();

        return $this->getService(SellableStock::class)->forPse(
            \Thelia\Model\ProductSaleElementsQuery::create()->findPk($product->getDefaultSaleElements()->getId(), $this->connection()),
            $physical,
            $cart?->getSaleUnitId(),
            $cartId > 0 ? $cartId : null,
        );
    }

    private function isHeld(CartItem $line): bool
    {
        return 1 === (int) $this->connection()->query('SELECT COALESCE(reserved_until > NOW(), 0) FROM cart_item WHERE id = '.(int) $line->getId())->fetchColumn();
    }

    private function storedReservedUntil(CartItem $line): ?string
    {
        $value = $this->connection()->query('SELECT reserved_until FROM cart_item WHERE id = '.(int) $line->getId())->fetchColumn();

        return null === $value || false === $value ? null : (string) $value;
    }

    private function secondsLeft(CartItem $line): int
    {
        return (int) $this->connection()->query('SELECT TIMESTAMPDIFF(SECOND, NOW(), reserved_until) FROM cart_item WHERE id = '.(int) $line->getId())->fetchColumn();
    }

    private function expire(CartItem $line): void
    {
        $this->connection()->exec('UPDATE cart_item SET reserved_until = NOW() - INTERVAL 1 MINUTE WHERE id = '.(int) $line->getId());
    }

    private function physicalOf(Product $product): float
    {
        return (float) $this->connection()->query('SELECT quantity FROM product_sale_elements WHERE id = '.(int) $product->getDefaultSaleElements()->getId())->fetchColumn();
    }

    private function connection(): \Propel\Runtime\Connection\ConnectionWrapper
    {
        return Propel::getWriteConnection(\Thelia\Model\Map\CartItemTableMap::DATABASE_NAME);
    }
}
