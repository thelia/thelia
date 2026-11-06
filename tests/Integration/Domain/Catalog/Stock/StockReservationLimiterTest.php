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
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;
use Thelia\Core\Event\ProductSaleElement\StockReservationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Stock\CallerTransactionInterface;
use Thelia\Domain\Catalog\Stock\DatabaseReservedQuantity;
use Thelia\Domain\Catalog\Stock\Exception\StockReservationRateLimitedException;
use Thelia\Domain\Catalog\Stock\Exception\StockReservationRefusedException;
use Thelia\Domain\Catalog\Stock\HeldCartLineReader;
use Thelia\Domain\Catalog\Stock\StockReservationAnnouncer;
use Thelia\Domain\Catalog\Stock\StockReservationLimiter;
use Thelia\Domain\Catalog\Stock\StockReservationService;
use Thelia\Domain\Catalog\Stock\StockRowLocker;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Order\Exception\StockShortageException;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Product;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Stock\RecordingStorage;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * The holds of the stock are capped: a hold locks the stock rows and writes the lines, so a caller
 * who repeats it, on one cart or across many from one address, could keep the stock out of every
 * real buyer. Over the budget, the checkout is refused with the delay to wait, and a change of the
 * cart leaves its line unheld, in silence.
 */
final class StockReservationLimiterTest extends ActionIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        $this->reserve('checkout');
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn($this->connection());
    }

    /** @var list<array{0: string, 1: callable}> */
    private array $listeners = [];

    protected function tearDown(): void
    {
        foreach ($this->listeners as [$eventName, $listener]) {
            $this->getService('event_dispatcher')->removeListener($eventName, $listener);
        }

        ConfigQuery::resetCache();
        $this->getService(TestCallerTransaction::class)->reset();

        parent::tearDown();
    }

    public function testAStepThatHoldsANewLineOverTheBudgetOfTheCartIsRefusedWithTheDelayToWaitAndIsNotAShortage(): void
    {
        $service = $this->serviceWithBudgets(cart: 2, client: 100);
        [$cart, $line] = $this->cartWith($this->product(5), 1);

        // Each step finds the line not held (a buyer back after the purge): a new hold each time.
        foreach ([1, 2] as $unused) {
            $service->holdForCheckout($cart);
            $this->connection()->exec('UPDATE cart_item SET reserved_until = NULL WHERE id = '.(int) $line->getId());
        }

        try {
            $service->holdForCheckout($cart);
            self::fail('The third new hold is over the budget of the cart.');
        } catch (StockReservationRateLimitedException $refusal) {
            self::assertGreaterThan(0, $refusal->retryAfterSeconds);
            self::assertLessThanOrEqual(3601, $refusal->retryAfterSeconds, 'The window is an hour, its end rounded up to the second.');
            self::assertNotInstanceOf(StockShortageException::class, $refusal, 'Not a shortage: nothing is missing, and no caller must say another cart took it.');
        }
    }

    /**
     * A budget of 60 an hour: 61 refreshes of a cart already held cost nothing and are all
     * answered; 61 holds of new lines are answered 60 times, the 61st is refused.
     */
    public function testRefreshingAHeldCartNeverSpendsTheBudgetAndTheSixtyFirstNewHoldIsRefused(): void
    {
        $service = $this->serviceWithBudgets(cart: 60, client: 1000);
        [$held, $heldLine] = $this->cartWith($this->product(5), 1);
        $service->holdForCheckout($held);

        foreach (range(1, 61) as $unused) {
            $service->holdForCheckout($held);
        }

        self::assertTrue($this->isHeld($heldLine), '61 refreshes of a cart already held: no refusal.');

        $this->factory->cartItem($held, $this->product(5), null, ['quantity' => 1.0]);
        $service->holdForCheckout($held);

        [$cart, $line] = $this->cartWith($this->product(5), 1);
        $refusedAt = [];

        foreach (range(1, 61) as $hold) {
            $this->connection()->exec('UPDATE cart_item SET reserved_until = NULL WHERE id = '.(int) $line->getId());

            try {
                $service->holdForCheckout($cart);
            } catch (StockReservationRateLimitedException) {
                $refusedAt[] = $hold;
            }
        }

        self::assertSame([61], $refusedAt, '61 new holds in the hour: the 61st is refused.');
    }

    /**
     * The budget is spent before anything is written: asking first and spending once the line is
     * held would let requests in parallel all find it unspent. The storage of the budget sees the
     * line at the moment it is spent: it must not be held yet.
     */
    public function testTheBudgetIsSpentBeforeTheLineIsWritten(): void
    {
        [$cart, $line] = $this->cartWith($this->product(5), 1);
        $seen = [];
        $storage = new RecordingStorage(function () use ($line, &$seen): void {
            $seen[] = $this->connection()->query('SELECT COUNT(*) FROM cart_item WHERE reserved_until IS NOT NULL AND id = '.(int) $line->getId())->fetchColumn();
        });
        $service = $this->serviceWithBudgets(cart: 5, client: 5, storage: $storage);

        $service->holdForCheckout($cart);

        self::assertNotSame([], $seen, 'The hold of a new line spends the budget.');
        self::assertSame(array_fill(0, \count($seen), 0), array_map('intval', $seen), 'Spent while the line was not held yet.');
        self::assertTrue($this->isHeld($line));
    }

    /**
     * Requests that each take a line of their own, from one address, with a budget of 3: three
     * are served and every other one is refused, whatever it asks for (one by one here: the order
     * of the spending is what the test above holds).
     */
    public function testRequestsThatEachTakeANewLineAreRefusedOnceTheBudgetIsSpent(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request(server: ['REMOTE_ADDR' => '203.0.113.21']));
        $service = $this->serviceWithBudgets(cart: 100, client: 3, requestStack: $requestStack);
        $held = 0;
        $refused = 0;

        foreach (range(1, 8) as $unused) {
            [$cart, $line] = $this->cartWith($this->product(5), 1);

            try {
                $service->holdForCheckout($cart);
            } catch (StockReservationRateLimitedException) {
                ++$refused;
            }

            $held += $this->isHeld($line) ? 1 : 0;
        }

        self::assertSame([3, 5], [$held, $refused]);
    }

    /**
     * Over the budget, the lines already held still go on: their date is pushed back and their
     * quantity changed; only the new line is left out. The checkout is refused (429) only because
     * of the new line.
     */
    public function testOverTheBudgetTheLinesAlreadyHeldAreStillPushedBackAndRequantifiedAndOnlyTheNewLineIsLeftOut(): void
    {
        $service = $this->serviceWithBudgets(cart: 1, client: 100);
        [$cart, $held] = $this->cartWith($this->product(10), 1);
        $service->holdForCheckout($cart);
        $this->connection()->exec('UPDATE cart_item SET reserved_until = NOW() + INTERVAL 1 MINUTE, quantity = 3 WHERE id = '.(int) $held->getId());
        $date = $this->reservedUntil($held);
        $new = $this->factory->cartItem($cart, $this->product(5), null, ['quantity' => 1.0]);
        $told = $this->listenToTheReservations();

        try {
            $service->holdForCheckout($cart);
            self::fail('The new line is over the budget.');
        } catch (StockReservationRateLimitedException) {
        }

        self::assertGreaterThan($date, $this->reservedUntil($held), 'The line already held has its date pushed back.');
        self::assertSame(3.0, $this->toldQuantity($held), 'And its quantity changed.');
        self::assertFalse($this->isHeld($new), 'The new line is left out.');
        self::assertSame([['released', 1.0], ['reserved', 3.0]], $told());

        // Nothing new to take: no refusal, though the budget is spent.
        $this->connection()->exec('DELETE FROM cart_item WHERE id = '.(int) $new->getId());
        $service->holdForCheckout($cart);
        self::assertTrue($this->isHeld($held));
    }

    public function testOverTheBudgetAChangeOfTheCartPushesBackTheLinesAlreadyHeldAndLeavesTheNewLineUnheldInSilence(): void
    {
        $this->reserve('cart');
        $service = $this->serviceWithBudgets(cart: 1, client: 100);
        [$cart, $held] = $this->cartWith($this->product(10), 1);
        $service->holdAfterCartChange($cart);
        $this->connection()->exec('UPDATE cart_item SET reserved_until = NOW() + INTERVAL 1 MINUTE WHERE id = '.(int) $held->getId());
        $date = $this->reservedUntil($held);
        $new = $this->factory->cartItem($cart, $this->product(5), null, ['quantity' => 1.0]);

        $service->holdAfterCartChange($cart);

        self::assertGreaterThan($date, $this->reservedUntil($held));
        self::assertFalse($this->isHeld($new));
    }

    /**
     * A line that cannot be held (out of stock) is never held, so it is never told: it costs no
     * token. The step refuses it, by name, at every change of the cart, whatever the budget says;
     * the line that can be held still pays, and the budget is intact for it.
     */
    public function testALineOutOfStockIsRefusedByNameAtEveryChangeAndSpendsNoBudget(): void
    {
        $service = $this->serviceWithBudgets(cart: 2, client: 100);
        [$cart, $held] = $this->cartWith($this->product(10), 1);
        $service->holdForCheckout($cart);
        $outOfStock = $this->product(0);
        $this->factory->cartItem($cart, $outOfStock, null, ['quantity' => 1.0]);

        foreach (range(1, 6) as $change) {
            $this->connection()->exec('UPDATE cart_item SET quantity = '.(1 + $change).' WHERE id = '.(int) $held->getId());

            try {
                $service->holdForCheckout($cart);
                self::fail('The line out of stock is refused.');
            } catch (StockReservationRateLimitedException) {
                self::fail('Change '.$change.': a line that cannot be held must be refused as a shortage, not as a budget spent.');
            } catch (StockReservationRefusedException $refusal) {
                self::assertStringContainsString((string) $outOfStock->getRef(), $refusal->getMessage(), 'The refusal names the line.');
            }
        }

        // The budget (2) was spent once, by the first hold: one new hold still goes through.
        $this->connection()->exec('DELETE FROM cart_item WHERE product_id = '.(int) $outOfStock->getId());
        $this->factory->cartItem($cart, $this->product(5), null, ['quantity' => 1.0]);
        $service->holdForCheckout($cart);

        // And the one after it is over the budget: a line that can be held is refused as such.
        $this->factory->cartItem($cart, $this->product(5), null, ['quantity' => 1.0]);
        $this->expectException(StockReservationRateLimitedException::class);
        $service->holdForCheckout($cart);
    }

    /**
     * A line told as held whose date ran out and whose stock another cart took since is refused by
     * name too (the transaction refuses it), even when a new line comes with it: no token is spent.
     */
    public function testAnExpiredLineTheStockNoLongerCoversIsRefusedByNameAndSpendsNoBudget(): void
    {
        $service = $this->serviceWithBudgets(cart: 2, client: 100);
        $product = $this->product(5);
        [$cart, $expired] = $this->cartWith($product, 2);
        $service->holdForCheckout($cart);
        $this->connection()->exec('UPDATE cart_item SET reserved_until = NOW() - INTERVAL 1 MINUTE WHERE id = '.(int) $expired->getId());

        // Another cart takes all that was left of the product while the date of the first ran out.
        [$other] = $this->cartWith($product, 5);
        $service->holdForCheckout($other);
        $this->factory->cartItem($cart, $this->product(5), null, ['quantity' => 1.0]);

        foreach (range(1, 6) as $change) {
            try {
                $service->holdForCheckout($cart);
                self::fail('The expired line is no longer covered: refused.');
            } catch (StockReservationRateLimitedException) {
                self::fail('Change '.$change.': refused as a budget spent, not as a shortage.');
            } catch (StockReservationRefusedException $refusal) {
                self::assertStringContainsString((string) $product->getRef(), $refusal->getMessage(), 'The refusal names the line.');
            }
        }

        // The budget (2) was spent once, by the first hold: the new line is still held at no refusal.
        $this->connection()->exec('DELETE FROM cart_item WHERE id = '.(int) $expired->getId());
        $service->holdForCheckout($cart);
    }

    /**
     * The line was told as held when the hold read it and a purge cleared it before the hold locked
     * it (read stale here by hand): over the budget, the hold does not start it, in the transaction
     * either; with budget left, it is a new reservation told as such.
     */
    public function testALineClearedBetweenTheReadAndTheLocksIsNotStartedOverTheBudget(): void
    {
        $this->reserve('checkout');
        $service = $this->serviceWithBudgets(cart: 100, client: 100);
        $told = $this->listenToTheReservations();
        [$cart, $line] = $this->cartWith($this->product(5), 2);
        $staleLine = [
            'id' => (int) $line->getId(),
            'productSaleElementsId' => (int) $line->getProductSaleElementsId(),
            'quantity' => 2.0,
            'bundle' => false,
            'virtual' => false,
            'reference' => 'ref',
            'held' => false,
            'told' => true,
        ];
        $holdInTransaction = new \ReflectionMethod(StockReservationService::class, 'holdInTransaction');

        $holdInTransaction->invoke($service, $cart, [$staleLine], false, false, $this->connection());

        self::assertFalse($this->isHeld($line), 'Over the budget, a line found not told under the locks stays unheld.');
        self::assertSame([], $told());

        $holdInTransaction->invoke($service, $cart, [$staleLine], false, true, $this->connection());

        self::assertTrue($this->isHeld($line));
        self::assertSame([['reserved', 2.0]], $told());
    }

    public function testChangingTheQuantityOfAHeldLineSpendsNothing(): void
    {
        $this->reserve('cart');
        $service = $this->serviceWithBudgets(cart: 2, client: 2);
        [$cart, $line] = $this->cartWith($this->product(100), 1);
        $service->holdAfterCartChange($cart);

        foreach (range(2, 40) as $quantity) {
            $this->connection()->exec('UPDATE cart_item SET quantity = '.$quantity.' WHERE id = '.(int) $line->getId());
            $service->holdAfterCartChange($cart);
        }

        self::assertTrue($this->isHeld($line));

        // The budget of 2 spent one hold of a new line: the quantities cost nothing, a second new line goes.
        $this->factory->cartItem($cart, $this->product(5), null, ['quantity' => 1.0]);
        $service->holdForCheckout($cart);
    }

    public function testTheBudgetOfTheAddressIsNotSpentByAHoldThatTakesNothingNew(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request(server: ['REMOTE_ADDR' => '203.0.113.9']));
        $service = $this->serviceWithBudgets(cart: 100, client: 2, requestStack: $requestStack);
        [$cart, $line] = $this->cartWith($this->product(5), 1);
        $service->holdForCheckout($cart);

        foreach (range(1, 5) as $unused) {
            $service->holdForCheckout($cart);
        }

        self::assertTrue($this->isHeld($line));

        [$other, $otherLine] = $this->cartWith($this->product(5), 1);
        $service->holdForCheckout($other);
        self::assertTrue($this->isHeld($otherLine), 'The budget of two: one spent by the first cart, none by its refreshes.');

        [$third] = $this->cartWith($this->product(5), 1);

        $this->expectException(StockReservationRateLimitedException::class);
        $service->holdForCheckout($third);
    }

    public function testAChangeOfTheCartOverTheBudgetLeavesTheNewLineUnheldInSilence(): void
    {
        $this->reserve('cart');
        $service = $this->serviceWithBudgets(cart: 1, client: 100);
        [$cart, $first] = $this->cartWith($this->product(5), 1);
        $service->holdAfterCartChange($cart);
        self::assertTrue($this->isHeld($first));
        $date = $this->reservedUntil($first);
        $second = $this->factory->cartItem($cart, $this->product(5), null, ['quantity' => 1.0]);

        $service->holdAfterCartChange($cart);

        self::assertFalse($this->isHeld($second), 'The line stays in the cart, unheld.');
        self::assertSame($date, $this->reservedUntil($first), 'What was held keeps its date.');
    }

    public function testTheAddressIsSpentFirstAndAnAddressOverItsBudgetSpendsNothingOfTheCart(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request(server: ['REMOTE_ADDR' => '203.0.113.7']));
        $service = $this->serviceWithBudgets(cart: 1, client: 2, requestStack: $requestStack);
        [$first] = $this->cartWith($this->product(5), 1);
        [$second] = $this->cartWith($this->product(5), 1);
        [$third, $line] = $this->cartWith($this->product(5), 1);
        $service->holdForCheckout($first);
        $service->holdForCheckout($second);

        try {
            $service->holdForCheckout($third);
            self::fail('The address is over its budget of two.');
        } catch (StockReservationRateLimitedException) {
        }

        $requestStack->pop();
        $service->holdForCheckout($third);

        self::assertTrue($this->isHeld($line), 'The refusal of the address left the budget of the cart untouched.');
    }

    public function testACommandHasNoAddressAndOnlyTheCartIsCapped(): void
    {
        $service = $this->serviceWithBudgets(cart: 100, client: 1);
        $carts = [];

        foreach (range(1, 3) as $unused) {
            [$carts[]] = $this->cartWith($this->product(5), 1);
        }

        foreach ($carts as $cart) {
            $service->holdForCheckout($cart);
        }

        self::assertTrue(true, 'Three carts held with a budget of one for the address: it does not apply with no request.');
    }

    public function testAHoldWithNothingToHoldSpendsNothing(): void
    {
        $service = $this->serviceWithBudgets(cart: 1, client: 100);
        $empty = $this->factory->cart(null, ['currency' => $this->factory->currency()]);

        foreach (range(1, 3) as $unused) {
            $service->holdForCheckout($empty);
        }

        [$cart, $line] = $this->cartWith($this->product(5), 1);
        $service->holdAfterCartChange($cart);
        self::assertFalse($this->isHeld($line), 'Checkout scope: nothing to hold from the cart yet, nothing spent.');

        $service->holdForCheckout($cart);

        self::assertTrue($this->isHeld($line));
    }

    /** The figures a shop gets when it sets nothing, and the ones the container builds from them. */
    public function testTheBudgetsOfTheShopAre60HoldsAnHourForACartAnd300ForAnAddress(): void
    {
        $builder = new ContainerBuilder();
        $file = \dirname(__DIR__, 5).'/core/lib/Thelia/Config/Resources/parameters/stock_reservation_rate_limit.php';
        $instanceof = [];
        (require $file)(new ContainerConfigurator($builder, new PhpFileLoader($builder, new FileLocator()), $instanceof, $file, $file));

        $parameters = $builder->getParameterBag()->all();
        self::assertSame('60', $parameters['env(THELIA_STOCK_RESERVATION_RATE_LIMIT_PER_CART)'] ?? null);
        self::assertSame('300', $parameters['env(THELIA_STOCK_RESERVATION_RATE_LIMIT_PER_CLIENT)'] ?? null);

        // The cart budget is the shop's own in the test environment too.
        $perCart = $this->getService('limiter.stock_reservation_per_cart');
        self::assertInstanceOf(RateLimiterFactoryInterface::class, $perCart);
        $limiter = $perCart->create((string) random_int(1_000_000_000, 2_000_000_000));

        for ($hold = 1; $hold <= 60; ++$hold) {
            self::assertTrue($limiter->consume()->isAccepted(), 'Hold '.$hold.' of the cart.');
        }

        self::assertFalse($limiter->consume()->isAccepted(), 'The 61st hold of the cart is over the budget.');
    }

    private function serviceWithBudgets(int $cart, int $client, ?RequestStack $requestStack = null, ?StorageInterface $storage = null): StockReservationService
    {
        $factory = static fn (string $id, int $limit): RateLimiterFactory => new RateLimiterFactory(
            ['id' => $id, 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 hour'],
            $storage ?? new InMemoryStorage(),
        );

        return new StockReservationService(
            $this->getService(StockSettings::class),
            $this->getService(DatabaseReservedQuantity::class),
            $this->getService(StockRowLocker::class),
            $this->getService(CallerTransactionInterface::class),
            new StockReservationLimiter($factory('cart', $cart), $factory('client', $client), $requestStack ?? new RequestStack()),
            $this->getService(StockReservationAnnouncer::class),
            $this->getService(HeldCartLineReader::class),
        );
    }

    private function reserve(string $scope): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, $scope);
        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '15');
        ConfigQuery::resetCache();
    }

    private function product(int $quantity): Product
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $product->getDefaultSaleElements()->setQuantity($quantity)->save($this->connection());

        return $product;
    }

    /**
     * @return array{0: Cart, 1: CartItem}
     */
    private function cartWith(Product $product, int $quantity): array
    {
        $cart = $this->factory->cart(null, ['currency' => $this->factory->currency()]);

        return [$cart, $this->factory->cartItem($cart, $product, null, ['quantity' => (float) $quantity])];
    }

    private function isHeld(CartItem $line): bool
    {
        return 1 === (int) $this->connection()->query('SELECT COALESCE(reserved_until > NOW(), 0) FROM cart_item WHERE id = '.(int) $line->getId())->fetchColumn();
    }

    private function toldQuantity(CartItem $line): ?float
    {
        $value = $this->connection()->query('SELECT reserved_quantity FROM cart_item WHERE id = '.(int) $line->getId())->fetchColumn();

        return false === $value || null === $value ? null : (float) $value;
    }

    /**
     * @return \Closure(): list<array{0: string, 1: float}> the direction and the quantity of each line told since, in order
     */
    private function listenToTheReservations(): \Closure
    {
        $told = [];
        $dispatcher = $this->getService('event_dispatcher');

        foreach ([TheliaEvents::STOCK_RESERVED => 'reserved', TheliaEvents::STOCK_RELEASED => 'released'] as $eventName => $direction) {
            $listener = static function (StockReservationEvent $event) use (&$told, $direction): void {
                foreach ($event->getLines() as $line) {
                    $told[] = [$direction, $line->quantity];
                }
            };
            $dispatcher->addListener($eventName, $listener);
            $this->listeners[] = [$eventName, $listener];
        }

        return static function () use (&$told): array {
            return $told;
        };
    }

    private function reservedUntil(CartItem $line): ?string
    {
        $value = $this->connection()->query('SELECT reserved_until FROM cart_item WHERE id = '.(int) $line->getId())->fetchColumn();

        return false === $value || null === $value ? null : (string) $value;
    }

    private function connection(): \Propel\Runtime\Connection\ConnectionWrapper
    {
        return Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME);
    }
}
