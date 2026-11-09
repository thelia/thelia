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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\ProductSaleElement\StockReservationEvent;
use Thelia\Core\Event\ProductSaleElement\StockReservationLine;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Cart\DTO\CartItemDeleteDTO;
use Thelia\Domain\Cart\DTO\CartItemUpdateQuantityDTO;
use Thelia\Domain\Cart\Service\CartPurger;
use Thelia\Domain\Cart\Service\CartQuantityReconciler;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Stock\Exception\StockReservationRefusedException;
use Thelia\Domain\Catalog\Stock\StockReservationAnnouncer;
use Thelia\Domain\Catalog\Stock\StockReservationPurger;
use Thelia\Domain\Catalog\Stock\StockReservationService;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Domain\Customer\Service\CustomerAnonymizer;
use Thelia\Domain\Promotion\Coupon\OfferedLine\OfferedLineProviderInterface;
use Thelia\Domain\Promotion\Coupon\OfferedLine\OfferedLineRequest;
use Thelia\Domain\Promotion\Coupon\Service\OfferedCartLineService;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Coupon;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Product;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\PlacesBundleOrders;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * What a cart starts or stops holding of the stock is told to the listeners, once, after the
 * write: the lines that were not held and are (STOCK_RESERVED), the lines that were held and are
 * not (STOCK_RELEASED). Holding again, to push the date back, is told to nobody.
 */
final class StockReservationEventsTest extends ActionIntegrationTestCase
{
    use PlacesBundleOrders;

    /** @var list<array{0: string, 1: int, 2: list<StockReservationLine>}> */
    private array $told = [];

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        $this->reserve('checkout');
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn($this->connection());

        foreach ([TheliaEvents::STOCK_RESERVED => 'reserved', TheliaEvents::STOCK_RELEASED => 'released'] as $eventName => $direction) {
            $this->listen($eventName, function (StockReservationEvent $event) use ($direction): void {
                $this->told[] = [$direction, $event->getCartId(), $event->getLines()];
            });
        }
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

    public function testACheckoutHoldTellsTheLinesItStartedToHoldInOneEventForTheCart(): void
    {
        $first = $this->product(5);
        $second = $this->product(5);
        [$cart, $lineOfFirst] = $this->cartWith($first, 2);
        $lineOfSecond = $this->factory->cartItem($cart, $second, null, ['quantity' => 3.0]);

        $this->checkout()->holdStock($cart);

        self::assertEquals(
            [['reserved', (int) $cart->getId(), [$this->lineOf($lineOfFirst, 2.0), $this->lineOf($lineOfSecond, 3.0)]]],
            $this->told,
            'One event for the cart, a line each, in the order of the lines.',
        );
    }

    public function testHoldingAgainTellsNothingUntilAnotherLineIsHeldAndThenTellsOnlyThatOne(): void
    {
        $product = $this->product(5);
        [$cart, $line] = $this->cartWith($product, 2);
        $this->checkout()->holdStock($cart);
        $this->told = [];

        $this->checkout()->holdStock($cart);

        self::assertSame([], $this->told, 'A line held again keeps its hold: the date moves, nothing is told.');

        $other = $this->factory->cartItem($cart, $this->product(5), null, ['quantity' => 1.0]);
        $this->checkout()->holdStock($cart);

        self::assertEquals([['reserved', (int) $cart->getId(), [$this->lineOf($other, 1.0)]]], $this->told);
        self::assertTrue($this->isHeld($line));
    }

    /**
     * A line is told as held from its first hold until it is released, whether or not its date ran
     * out meanwhile: holding it again only pushes the date. Reserved 2, the date runs out, held again
     * tells nothing; removed, it is released 2. One reservation, one release.
     */
    public function testAHoldThatTakesBackALineWhoseDateRanOutTellsNothingAndItsRemovalTellsItReleased(): void
    {
        $product = $this->product(5);
        [$cart, $line] = $this->cartWith($product, 2);
        $this->checkout()->holdStock($cart);
        self::assertEquals([['reserved', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]], $this->told);
        $this->expire($line);
        $this->told = [];

        $this->checkout()->holdStock($cart);

        self::assertSame([], $this->told, 'The line was never released: its date only moves.');
        self::assertTrue($this->isHeld($line));

        $this->getService(CartFacade::class)->removeItem(new CartItemDeleteDTO($cart, (int) $line->getId()));

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]], $this->told);
    }

    public function testAStepTheStockRefusesTellsNothingAndHoldsNothing(): void
    {
        [$cart, $covered] = $this->cartWith($this->product(5), 1);
        $this->factory->cartItem($cart, $this->product(0), null, ['quantity' => 1.0]);

        try {
            $this->checkout()->holdStock($cart);
            self::fail('The second line is not covered.');
        } catch (StockReservationRefusedException) {
        }

        self::assertSame([], $this->told, 'The hold was rolled back: no line is held, none is told.');
        self::assertFalse($this->isHeld($covered));
    }

    public function testALineTheStockNoLongerCoversIsToldAsReleasedAndAnUnheldOneIsToldNothing(): void
    {
        $this->reserve('cart');
        $product = $this->product(3);
        [$cart, $line] = $this->cartWith($product, 2);
        $service = $this->getService(StockReservationService::class);
        $service->holdAfterCartChange($cart);
        self::assertEquals([['reserved', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]], $this->told);
        $this->told = [];

        $this->connection()->exec('UPDATE product_sale_elements SET quantity = 1 WHERE id = '.(int) $line->getProductSaleElementsId());
        $service->holdAfterCartChange($cart);

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]], $this->told);
        self::assertFalse($this->isHeld($line));
        $this->told = [];

        $service->holdAfterCartChange($cart);

        self::assertSame([], $this->told, 'A line that is not held is not released again.');
    }

    public function testAnOrderTellsTheLinesItConsumedOnceTheTransactionOfThePlacementIsOver(): void
    {
        $this->manageStockOnCreation();
        $product = $this->product(2);
        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $line = $this->factory->cartItem($cart, $product, null, ['quantity' => 1.0]);
        $this->checkout()->holdStock($cart);
        $this->told = [];

        $this->placeOrder($cart, $customer, $currency, $lang);
        $this->getService(StockReservationAnnouncer::class)->flush();

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 1.0)]]], $this->told);
        self::assertFalse($this->isHeld($line));

        $this->getService(StockReservationAnnouncer::class)->flush();

        self::assertCount(1, $this->told, 'Told once.');
    }

    public function testRemovingAHeldLineTellsItAsReleasedAndRemovingAnUnheldLineTellsNothing(): void
    {
        [$cart, $held] = $this->cartWith($this->product(5), 2);
        $unheld = $this->factory->cartItem($cart, $this->product(5), null, ['quantity' => 1.0]);
        $this->checkout()->holdStock($cart);
        $this->connection()->exec('UPDATE cart_item SET reserved_until = NULL WHERE id = '.(int) $unheld->getId());
        $this->told = [];
        $facade = $this->getService(CartFacade::class);

        $facade->removeItem(new CartItemDeleteDTO($cart, (int) $unheld->getId()));

        self::assertSame([], $this->told, 'The line held nothing.');

        $facade->removeItem(new CartItemDeleteDTO($cart, (int) $held->getId()));

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($held, 2.0)]]], $this->told);
    }

    public function testRemovingALineWhoseHoldRanOutTellsItReleased(): void
    {
        [$cart, $line] = $this->cartWith($this->product(5), 2);
        $this->checkout()->holdStock($cart);
        $this->expire($line);
        $this->told = [];

        $this->getService(CartFacade::class)->removeItem(new CartItemDeleteDTO($cart, (int) $line->getId()));

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]], $this->told, 'Nothing released it before: the purge no longer finds it.');
    }

    public function testEmptyingACartWhoseHoldsRanOutTellsItsLinesReleased(): void
    {
        [$cart, $line] = $this->cartWith($this->product(5), 2);
        $this->checkout()->holdStock($cart);
        $this->expire($line);
        $this->told = [];

        $this->dispatcher->dispatch(new CartEvent($cart), TheliaEvents::CART_CLEAR);

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]], $this->told);
    }

    public function testAnOrderTellsTheLinesItConsumedWhoseHoldRanOutAsReleased(): void
    {
        $this->manageStockOnCreation();
        $product = $this->product(2);
        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $line = $this->factory->cartItem($cart, $product, null, ['quantity' => 1.0]);
        $this->checkout()->holdStock($cart);
        $this->expire($line);
        $this->told = [];

        $this->placeOrder($cart, $customer, $currency, $lang);
        $this->getService(StockReservationAnnouncer::class)->flush();

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 1.0)]]], $this->told);
    }

    public function testThePurgeOfACartWhoseHoldRanOutTellsItsLinesReleased(): void
    {
        [$customer, $currency] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $line = $this->factory->cartItem($cart, $this->product(5), null, ['quantity' => 2.0]);
        $this->checkout()->holdStock($cart);
        $this->expire($line);
        $this->changeQuantityWithNoHold($line, 4.0);
        $this->connection()->exec('UPDATE cart SET created_at = NOW() - INTERVAL 30 DAY WHERE id = '.(int) $cart->getId());
        $this->told = [];

        $this->getService(CartPurger::class)->purgeCartsWithoutOrder(7);

        self::assertContainsEquals(['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]], $this->told);
    }

    /**
     * Purging the carts: the carts that held nothing go without a word, the ones that held something
     * are told once each, however many lots it takes.
     */
    public function testThePurgeOfCartsTellsOnlyTheOnesThatHeldAndDeletesThemAll(): void
    {
        [$customer, $currency] = $this->aBuyer();
        $held = $this->aCart($customer, $currency);
        $heldLine = $this->factory->cartItem($held, $this->product(5), null, ['quantity' => 1.0]);
        $this->checkout()->holdStock($held);
        $this->changeQuantityWithNoHold($heldLine, 6.0);
        $plain = $this->aCart($customer, $currency);
        $plainLine = $this->factory->cartItem($plain, $this->product(5), null, ['quantity' => 1.0]);
        $this->connection()->exec('UPDATE cart SET created_at = NOW() - INTERVAL 30 DAY WHERE id IN ('.(int) $held->getId().', '.(int) $plain->getId().')');
        $this->told = [];

        $deleted = $this->getService(CartPurger::class)->purgeCartsWithoutOrder(7);

        self::assertGreaterThanOrEqual(2, $deleted);
        self::assertEquals([['released', (int) $held->getId(), [$this->lineOf($heldLine, 1.0)]]], array_values(array_filter($this->told, static fn (array $told): bool => \in_array($told[1], [(int) $held->getId(), (int) $plain->getId()], true))));
        self::assertSame(0, (int) $this->connection()->query('SELECT COUNT(*) FROM cart WHERE id IN ('.(int) $held->getId().', '.(int) $plain->getId().')')->fetchColumn());
        self::assertSame(0, (int) $this->connection()->query('SELECT COUNT(*) FROM cart_item WHERE id = '.(int) $plainLine->getId())->fetchColumn());
    }

    /**
     * The quantity of a held line goes from 2 to 5: 2 is told released, 5 is told reserved, and the
     * release that follows tells 5. Net for a module that counts: 0.
     */
    public function testChangingTheQuantityOfAHeldLineTellsTheOldQuantityReleasedThenTheNewOneReserved(): void
    {
        [$cart, $line] = $this->cartWith($this->product(10), 2);
        $this->checkout()->holdStock($cart);
        $this->told = [];

        $this->getService(CartFacade::class)->updateItemQuantity(new CartItemUpdateQuantityDTO($cart, (int) $line->getId(), 5));

        self::assertEquals([
            ['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]],
            ['reserved', (int) $cart->getId(), [$this->lineOf($line, 5.0)]],
        ], $this->told);
        self::assertTrue($this->isHeld($line));
        $this->told = [];

        $this->getService(CartFacade::class)->removeItem(new CartItemDeleteDTO($cart, (int) $line->getId()));

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 5.0)]]], $this->told);
    }

    public function testAddingToAHeldLineTellsTheSameChange(): void
    {
        $product = $this->product(10);
        [$cart, $line] = $this->cartWith($product, 2);
        $this->checkout()->holdStock($cart);
        $this->told = [];

        $this->getService(CartFacade::class)->addItem(new CartItemAddDTO($cart, (int) $product->getId(), (int) $line->getProductSaleElementsId(), 3));

        self::assertEquals([
            ['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]],
            ['reserved', (int) $cart->getId(), [$this->lineOf($line, 5.0)]],
        ], $this->told);
    }

    public function testAQuantityThatStaysWhatItWasTellsNothing(): void
    {
        [$cart, $line] = $this->cartWith($this->product(10), 2);
        $this->checkout()->holdStock($cart);
        $this->told = [];

        $this->getService(CartFacade::class)->updateItemQuantity(new CartItemUpdateQuantityDTO($cart, (int) $line->getId(), 2));

        self::assertSame([], $this->told, 'Held again with the quantity it was told with: its date moves, nothing is told.');
    }

    public function testAChangedQuantityTheStockDoesNotCoverIsToldReleasedWithTheQuantityItWasToldWith(): void
    {
        $this->reserve('cart');
        [$cart, $line] = $this->cartWith($this->product(5), 2);
        $service = $this->getService(StockReservationService::class);
        $service->holdAfterCartChange($cart);
        $this->told = [];

        $this->connection()->exec('UPDATE cart_item SET quantity = 4 WHERE id = '.(int) $line->getId());
        $this->connection()->exec('UPDATE product_sale_elements SET quantity = 3 WHERE id = '.(int) $line->getProductSaleElementsId());
        $service->holdAfterCartChange($cart);

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]], $this->told, 'The 2 it was told with, not the 4 it asks now.');
        self::assertFalse($this->isHeld($line));
    }

    public function testAGiftLineThatHeldStockAndGoesIsToldReleased(): void
    {
        [$cart, $gift] = $this->giftHeldInACart(1.0);
        $this->changeQuantityWithNoHold($gift, 4.0);

        $this->getService(OfferedCartLineService::class)->reconcile([], $cart);

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($gift, 1.0)]]], $this->told);
        self::assertSame(0, (int) $this->connection()->query('SELECT COUNT(*) FROM cart_item WHERE id = '.(int) $gift->getId())->fetchColumn());
    }

    /**
     * The promotion changes the quantity of its gift line and no hold follows at once: nothing is
     * told, the line keeps the quantity it was told with, and the next hold tells the change as a
     * release of the old quantity and a reservation of the new one, without spending the budget:
     * it is not a new line.
     */
    public function testAGiftLineWhoseQuantityChangesWhileHeldIsToldTheChangeByTheNextHoldAndItIsFree(): void
    {
        [$cart, $gift, $product, $promotion] = $this->giftHeldInACart(1.0);
        $offer = $this->createStubForIntersectionOfInterfaces([CouponInterface::class, OfferedLineProviderInterface::class]);
        $offer->method('getOfferedLineRequests')->willReturn([new OfferedLineRequest((int) $product->getId(), 3, (int) $promotion->getId())]);
        $offer->method('getTitle')->willReturn('Gift');
        $offer->method('getCode')->willReturn('GIFT');

        $this->getService(OfferedCartLineService::class)->reconcile([$offer], $cart);

        self::assertSame([], $this->told, 'No hold followed: nothing changed in what the listeners were told.');
        self::assertSame(1.0, $this->toldQuantity($gift));
        $budget = $this->cartBudgetLeft($cart);

        $this->checkout()->holdStock($cart);

        self::assertEquals([
            ['released', (int) $cart->getId(), [$this->lineOf($gift, 1.0)]],
            ['reserved', (int) $cart->getId(), [$this->lineOf($gift, 3.0)]],
        ], $this->told);
        self::assertSame($budget, $this->cartBudgetLeft($cart), 'A change of quantity of a held gift line is not a new hold.');
    }

    /**
     * Reserved 2; a module sets 5 with no hold; removed: released 2, the quantity the listeners
     * were told with, not the 5 the line asks now.
     */
    public function testAQuantityChangedByAModuleWithNoHoldIsToldReleasedWithTheQuantityItWasReservedWith(): void
    {
        [$cart, $line] = $this->cartWith($this->product(10), 2);
        $this->checkout()->holdStock($cart);
        self::assertSame(2.0, $this->toldQuantity($line));
        $this->told = [];

        $this->changeQuantityWithNoHold($line, 5.0);

        self::assertSame([], $this->told);
        self::assertSame(2.0, $this->toldQuantity($line), 'Written by a hold only.');

        $this->getService(CartFacade::class)->removeItem(new CartItemDeleteDTO($cart, (int) $line->getId()));

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]], $this->told);
    }

    public function testAHoldThatChangesTheQuantityOfAHeldLineWritesTheNewQuantityAndAReleaseEmptiesIt(): void
    {
        [$cart, $line] = $this->cartWith($this->product(10), 2);
        $this->checkout()->holdStock($cart);
        $this->changeQuantityWithNoHold($line, 5.0);

        $this->checkout()->holdStock($cart);

        self::assertSame(5.0, $this->toldQuantity($line));

        $this->getService(StockReservationService::class)->releaseCart((int) $cart->getId(), $this->connection());

        self::assertNull($this->toldQuantity($line), 'Nothing is told as held any more.');
        self::assertSame(0, (int) $this->connection()->query('SELECT COUNT(*) FROM cart_item WHERE reserved_until IS NOT NULL AND id = '.(int) $line->getId())->fetchColumn());
    }

    /** The order consumes the cart: released with the quantity the lines were reserved with. */
    public function testTheReleaseOfAnOrderedCartTellsTheQuantitiesTheLinesWereReservedWith(): void
    {
        [$cart, $line] = $this->cartWith($this->product(10), 2);
        $other = $this->factory->cartItem($cart, $this->product(10), null, ['quantity' => 3.0]);
        $this->checkout()->holdStock($cart);
        $this->changeQuantityWithNoHold($line, 7.0);
        $this->changeQuantityWithNoHold($other, 1.0);
        $this->told = [];

        $this->getService(StockReservationService::class)->releaseCart((int) $cart->getId(), $this->connection());

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 2.0), $this->lineOf($other, 3.0)]]], $this->told);
    }

    /**
     * A hold that fails after the quantity changed (here, the step is refused: the stock does not
     * cover the new quantity) rolls back and keeps what the line was told with: the hold that
     * follows tells the right pair.
     */
    public function testAHoldThatFailsAfterAQuantityChangeLosesNothingOfWhatTheLineWasToldWith(): void
    {
        [$cart, $line] = $this->cartWith($this->product(3), 2);
        $service = $this->getService(StockReservationService::class);
        $service->holdForCheckout($cart);
        $this->told = [];

        $this->changeQuantityWithNoHold($line, 5.0);

        try {
            $service->holdForCheckout($cart);
            self::fail('The stock does not cover 5.');
        } catch (StockReservationRefusedException) {
        }

        self::assertSame([], $this->told);
        self::assertSame(2.0, $this->toldQuantity($line));

        $this->changeQuantityWithNoHold($line, 3.0);
        $service->holdForCheckout($cart);

        self::assertEquals([
            ['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]],
            ['reserved', (int) $cart->getId(), [$this->lineOf($line, 3.0)]],
        ], $this->told);
    }

    public function testTheCartsOfACustomerAnonymizedAreToldReleasedForTheirHeldLines(): void
    {
        [$customer, $currency] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $line = $this->factory->cartItem($cart, $this->product(5), null, ['quantity' => 2.0]);
        $this->checkout()->holdStock($cart);
        $this->changeQuantityWithNoHold($line, 4.0);
        $this->told = [];

        $this->getService(CustomerAnonymizer::class)->anonymize($customer);
        $this->getService(StockReservationAnnouncer::class)->flush();

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]], $this->told);
    }

    public function testEmptyingTheCartTellsItsHeldLinesInOneEvent(): void
    {
        [$cart, $first] = $this->cartWith($this->product(5), 2);
        $second = $this->factory->cartItem($cart, $this->product(5), null, ['quantity' => 1.0]);
        $this->checkout()->holdStock($cart);
        $this->changeQuantityWithNoHold($first, 4.0);
        $this->changeQuantityWithNoHold($second, 3.0);
        $this->told = [];

        $this->dispatcher->dispatch(new CartEvent($cart), TheliaEvents::CART_CLEAR);

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($first, 2.0), $this->lineOf($second, 1.0)]]], $this->told);
    }

    public function testALineTheSaleUnitSwitchRemovesIsToldAsReleased(): void
    {
        $currency = $this->factory->currency();
        $first = $this->factory->saleUnit();
        $second = $this->factory->saleUnit();
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency, ['baseQuantity' => 100, 'basePrice' => 10.0]);
        $cart = $this->factory->cart(null, ['currency' => $currency]);
        $cart->setSaleUnitId($first->getId())->save($this->connection());
        $line = $this->factory->cartItem($cart, $product, $product->getDefaultSaleElements(), ['quantity' => 2.0, 'price' => '10.000000', 'promoPrice' => '10.000000']);
        $this->checkout()->holdStock($cart);
        $this->told = [];

        // The second unit has none of the product left: the line goes.
        $this->factory->saleUnitProductSaleElements($second, $product->getDefaultSaleElements(), 0.0);
        $this->getService(SaleUnitContext::class)->setSaleUnit($second);

        try {
            $this->dispatcher->dispatch(new CartEvent($cart), TheliaEvents::CART_FOLLOW_SALE_UNIT);
            $this->getService(StockReservationAnnouncer::class)->flush();
        } finally {
            $this->getService(SaleUnitContext::class)->reset();
        }

        self::assertContainsEquals(['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]], $this->told);
        self::assertSame(0, (int) $this->connection()->query('SELECT COUNT(*) FROM cart_item WHERE id = '.(int) $line->getId())->fetchColumn());
    }

    public function testALineTheSaleUnitSwitchLeavesUnderTheMinimumOfItsRulesIsToldAsReleased(): void
    {
        $currency = $this->factory->currency();
        $first = $this->factory->saleUnit();
        $second = $this->factory->saleUnit();
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $currency, ['baseQuantity' => 100, 'basePrice' => 10.0]);
        $product->getDefaultSaleElements()->setMinQuantity('2.000000')->save($this->connection());
        $cart = $this->factory->cart(null, ['currency' => $currency]);
        $cart->setSaleUnitId($first->getId())->save($this->connection());
        $line = $this->factory->cartItem($cart, $product, $product->getDefaultSaleElements(), ['quantity' => 2.0, 'price' => '10.000000', 'promoPrice' => '10.000000']);
        $this->checkout()->holdStock($cart);
        $this->told = [];

        // The second unit has one left: the line could keep one, which is under the minimum of two.
        $this->factory->saleUnitProductSaleElements($second, $product->getDefaultSaleElements(), 1.0);
        $this->getService(SaleUnitContext::class)->setSaleUnit($second);

        try {
            $this->dispatcher->dispatch(new CartEvent($cart), TheliaEvents::CART_FOLLOW_SALE_UNIT);
            $this->getService(StockReservationAnnouncer::class)->flush();
        } finally {
            $this->getService(SaleUnitContext::class)->reset();
        }

        self::assertSame(0, (int) $this->connection()->query('SELECT COUNT(*) FROM cart_item WHERE id = '.(int) $line->getId())->fetchColumn());
        self::assertContainsEquals(['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]], $this->told);
    }

    public function testAHeldLineTheQuantityRulesRemoveOnReadingTheCartBackIsToldAsReleased(): void
    {
        $product = $this->product(5);
        [$cart, $line] = $this->cartWith($product, 2);
        $this->checkout()->holdStock($cart);
        $this->told = [];

        // The rules of the combination changed since: nothing under three is sold any more.
        $product->getDefaultSaleElements()->setMinQuantity('3.000000')->setQuantityStep('3.000000')->save($this->connection());

        $this->getService(CartQuantityReconciler::class)->reconcile($cart, $this->dispatcher);
        $this->getService(StockReservationAnnouncer::class)->flush();

        self::assertSame(0, (int) $this->connection()->query('SELECT COUNT(*) FROM cart_item WHERE id = '.(int) $line->getId())->fetchColumn());
        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]], $this->told);
    }

    public function testThePurgeTellsTheHeldLinesOfTheCartsItDeletes(): void
    {
        [$customer, $currency] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $line = $this->factory->cartItem($cart, $this->product(5), null, ['quantity' => 2.0]);
        $this->checkout()->holdStock($cart);
        $this->changeQuantityWithNoHold($line, 4.0);
        $this->connection()->exec('UPDATE cart SET created_at = NOW() - INTERVAL 30 DAY WHERE id = '.(int) $cart->getId());
        $this->told = [];

        $deleted = $this->getService(CartPurger::class)->purgeCartsWithoutOrder(7);

        self::assertGreaterThanOrEqual(1, $deleted);
        self::assertContainsEquals(['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]], $this->told);
        self::assertSame(0, (int) $this->connection()->query('SELECT COUNT(*) FROM cart_item WHERE id = '.(int) $line->getId())->fetchColumn());
    }

    public function testAListenerThatFailsIsLoggedAndInterruptsNothing(): void
    {
        $this->listen(TheliaEvents::STOCK_RESERVED, static function (): never {
            throw new \RuntimeException('A module broke.');
        }, 10);
        [$cart, $line] = $this->cartWith($this->product(5), 2);

        $this->checkout()->holdStock($cart);

        self::assertTrue($this->isHeld($line), 'The stock stays held.');
    }

    public function testAShopThatDoesNotReserveTellsNothing(): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '0');
        ConfigQuery::resetCache();
        [$cart, $line] = $this->cartWith($this->product(5), 2);

        $this->checkout()->holdStock($cart);
        $this->getService(CartFacade::class)->removeItem(new CartItemDeleteDTO($cart, (int) $line->getId()));

        self::assertSame([], $this->told);
    }

    public function testThePurgeTellsTheLinesItClearsOneEventPerCartAndNothingForALineStillRunning(): void
    {
        $first = $this->product(5);
        [$cartA, $expiredA] = $this->cartWith($first, 1);
        $expiredA2 = $this->factory->cartItem($cartA, $this->product(5), null, ['quantity' => 2.0]);
        [$cartB, $expiredB] = $this->cartWith($this->product(5), 1);
        [$cartC, $running] = $this->cartWith($this->product(5), 1);

        foreach ([$cartA, $cartB, $cartC] as $cart) {
            $this->checkout()->holdStock($cart);
        }

        $ids = implode(',', [(int) $expiredA->getId(), (int) $expiredA2->getId(), (int) $expiredB->getId()]);
        $this->connection()->exec('UPDATE cart_item SET reserved_until = NOW() - INTERVAL 1 MINUTE WHERE id IN ('.$ids.')');
        $this->changeQuantityWithNoHold($expiredA2, 9.0);
        $this->changeQuantityWithNoHold($expiredB, 7.0);
        $this->told = [];

        $cleared = $this->getService(StockReservationPurger::class)->purgeExpired(2);

        self::assertSame(3, $cleared);
        self::assertEquals(
            [
                ['released', (int) $cartA->getId(), [$this->lineOf($expiredA, 1.0), $this->lineOf($expiredA2, 2.0)]],
                ['released', (int) $cartB->getId(), [$this->lineOf($expiredB, 1.0)]],
            ],
            $this->told,
            'One event per cart, for the cleared lines only; the batches of two do not split a cart in the telling.',
        );
        self::assertTrue($this->isHeld($running));

        $this->told = [];
        self::assertSame(0, $this->getService(StockReservationPurger::class)->purgeExpired());
        self::assertSame([], $this->told, 'Told once.');
    }

    public function testTheCopyOfACartAtSignInTellsTheLinesTheDeletedCartHeld(): void
    {
        $product = $this->product(5);
        [$customer, $currency] = $this->aBuyer();
        $cart = $this->factory->cart($customer, ['currency' => $currency]);
        $line = $this->factory->cartItem($cart, $product, null, ['quantity' => 2.0]);
        $this->checkout()->holdStock($cart);
        $this->changeQuantityWithNoHold($line, 3.0);
        $this->told = [];
        $oldCartId = (int) $cart->getId();

        $this->getService(RequestStack::class)->getMainRequest()->cookies->set(ConfigQuery::read('cart.cookie_name', 'thelia_cart'), $cart->getToken());
        $this->getService(SecurityContext::class)->setCustomerUser($customer);

        $event = new CartRestoreEvent();
        $this->dispatcher->dispatch($event, TheliaEvents::CART_RESTORE_CURRENT);

        self::assertNotSame($oldCartId, (int) $event->getCart()->getId(), 'The cart was copied.');
        self::assertEquals([['released', $oldCartId, [$this->lineOf($line, 2.0)]]], array_values(array_filter($this->told, static fn (array $told): bool => 'released' === $told[0])));
        self::assertFalse($this->isHeld($line));
    }

    /**
     * A line untouched for two reservation durations after its date ran out is held again as a new
     * one: its old hold is told released, the new one reserved, and it spends the budget.
     */
    public function testALineHeldAgainLongAfterItsDateRanOutIsToldReleasedThenReservedAndSpendsTheBudget(): void
    {
        [$cart, $line] = $this->cartWith($this->product(5), 2);
        $this->checkout()->holdStock($cart);
        $this->connection()->exec('UPDATE cart_item SET reserved_since = reserved_since - INTERVAL 60 MINUTE, reserved_until = NOW() - INTERVAL 31 MINUTE WHERE id = '.(int) $line->getId());
        $budget = $this->cartBudgetLeft($cart);
        $this->told = [];

        $this->checkout()->holdStock($cart);

        self::assertEquals(
            [['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]], ['reserved', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]],
            $this->told,
        );
        self::assertTrue($this->isHeld($line));
        self::assertSame($budget - 1, $this->cartBudgetLeft($cart), 'A new hold spends the budget.');
        self::assertSame(1, (int) $this->connection()->query('SELECT reserved_since >= NOW() - INTERVAL 1 MINUTE FROM cart_item WHERE id = '.(int) $line->getId())->fetchColumn(), 'Its longest hold counts from now.');
    }

    public function testALineHeldBeforeTheReservationWasTurnedOffIsToldReleasedWhenRemoved(): void
    {
        [$cart, $line] = $this->cartWith($this->product(5), 2);
        $this->checkout()->holdStock($cart);
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '0');
        $this->told = [];

        $this->getService(CartFacade::class)->removeItem(new CartItemDeleteDTO($cart, (int) $line->getId()));

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]], $this->told);
    }

    public function testALineHeldBeforeTheStockCheckWasTurnedOffIsToldReleasedByThePurge(): void
    {
        [$cart, $line] = $this->cartWith($this->product(5), 2);
        $this->checkout()->holdStock($cart);
        $this->expire($line);
        ConfigQuery::write('check-available-stock', '0');
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '0');
        $this->told = [];

        $this->getService(StockReservationPurger::class)->purgeExpired();

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]], $this->told);
        self::assertNull($this->toldQuantity($line), 'The purge cleared it.');
    }

    public function testAHeldLineTakenOutWhenItsProductBecomesAPackIsToldReleased(): void
    {
        $product = $this->product(5);
        [$cart, $line] = $this->cartWith($product, 2);
        $this->checkout()->holdStock($cart);
        $this->told = [];

        $this->getService(BundleCompositionService::class)->setBundleSettings((int) $product->getId(), true);
        $this->getService(StockReservationAnnouncer::class)->flush();

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]], $this->told);
    }

    public function testTheHeldLinesOfADeletedProductAreToldReleased(): void
    {
        $product = $this->product(5);
        [$cart, $line] = $this->cartWith($product, 2);
        $this->checkout()->holdStock($cart);
        $this->told = [];

        $product->delete($this->connection());
        $this->getService(StockReservationAnnouncer::class)->flush();

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]], $this->told);
    }

    public function testTheHeldLinesOfADeletedSaleElementAreToldReleased(): void
    {
        $product = $this->product(5);
        [$cart, $line] = $this->cartWith($product, 2);
        $this->checkout()->holdStock($cart);
        $this->told = [];

        $product->getDefaultSaleElements()->delete($this->connection());
        $this->getService(StockReservationAnnouncer::class)->flush();

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]], $this->told);
    }

    public function testTheHeldLinesOfADeletedCustomerAreToldReleased(): void
    {
        [$customer, $currency] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $line = $this->factory->cartItem($cart, $this->product(5), null, ['quantity' => 2.0]);
        $this->checkout()->holdStock($cart);
        $this->told = [];

        $customer->delete($this->connection());
        $this->getService(StockReservationAnnouncer::class)->flush();

        self::assertEquals([['released', (int) $cart->getId(), [$this->lineOf($line, 2.0)]]], $this->told);
    }

    private function reserve(string $scope): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, $scope);
        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '15');
        ConfigQuery::resetCache();
    }

    private function manageStockOnCreation(): void
    {
        $eventName = TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque');
        $this->listen($eventName, static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(true), 512);
    }

    private function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function checkout(): CheckoutFacade
    {
        return $this->getService(CheckoutFacade::class);
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

    private function lineOf(CartItem $line, float $quantity): StockReservationLine
    {
        return new StockReservationLine((int) $line->getId(), (int) $line->getProductSaleElementsId(), $quantity);
    }

    /**
     * @return array{0: Cart, 1: CartItem, 2: Product, 3: Coupon} a cart whose gift line holds stock, nothing told since
     */
    private function giftHeldInACart(float $quantity): array
    {
        $promotion = $this->factory->coupon();
        $product = $this->product(10);
        [$customer, $currency] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $gift = $this->factory->cartItem($cart, $product, null, ['quantity' => $quantity, 'isOffered' => 1, 'offeredByCouponId' => $promotion->getId()]);
        $this->checkout()->holdStock($cart);
        self::assertTrue($this->isHeld($gift));
        $this->told = [];

        return [$cart, $gift, $product, $promotion];
    }

    /** A module, an import or a back office that writes the quantity of a line by itself, with no hold after it. */
    private function changeQuantityWithNoHold(CartItem $line, float $quantity): void
    {
        $this->connection()->exec('UPDATE cart_item SET quantity = '.$quantity.' WHERE id = '.(int) $line->getId());
    }

    private function toldQuantity(CartItem $line): ?float
    {
        $value = $this->connection()->query('SELECT reserved_quantity FROM cart_item WHERE id = '.(int) $line->getId())->fetchColumn();

        return false === $value || null === $value ? null : (float) $value;
    }

    private function cartBudgetLeft(Cart $cart): int
    {
        $limiter = $this->getService('limiter.stock_reservation_per_cart');
        self::assertInstanceOf(RateLimiterFactoryInterface::class, $limiter);

        return $limiter->create((string) $cart->getId())->consume(0)->getRemainingTokens();
    }

    private function expire(CartItem $line): void
    {
        $this->connection()->exec('UPDATE cart_item SET reserved_until = NOW() - INTERVAL 1 MINUTE WHERE id = '.(int) $line->getId());
    }

    private function isHeld(CartItem $line): bool
    {
        return 1 === (int) $this->connection()->query('SELECT COALESCE(reserved_until > NOW(), 0) FROM cart_item WHERE id = '.(int) $line->getId())->fetchColumn();
    }

    private function connection(): \Propel\Runtime\Connection\ConnectionWrapper
    {
        return Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME);
    }
}
