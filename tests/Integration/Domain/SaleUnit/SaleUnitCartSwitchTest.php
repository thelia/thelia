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

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Cart\CartCreateEvent;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Cart\CartSaleUnitSwitchedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Product;
use Thelia\Model\SaleUnit;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A cart remembers the sale unit it was priced and stocked for. When the visitor
 * reaches another unit, the cart is checked against it before anything reads it:
 * a line the unit cannot sell is removed, a quantity beyond its stock reduced,
 * every line takes the unit's price, and an event tells what changed, which the
 * theme reads back from the flash bag on the next page.
 */
final class SaleUnitCartSwitchTest extends ActionIntegrationTestCase
{
    use RecordsSqlQueries;

    private Currency $currency;

    private SaleUnit $first;

    private SaleUnit $second;

    /** @var list<CartSaleUnitSwitchedEvent> */
    private array $switches = [];

    private ?\Closure $listener = null;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check-available-stock', '1');

        $this->currency = $this->factory->currency();
        $this->first = $this->factory->saleUnit();
        $this->second = $this->factory->saleUnit();

        $this->listener = function (CartSaleUnitSwitchedEvent $event): void {
            $this->switches[] = $event;
        };
        $this->dispatcher->addListener(TheliaEvents::CART_SALE_UNIT_SWITCHED, $this->listener);
    }

    protected function tearDown(): void
    {
        $this->dispatcher->removeListener(TheliaEvents::CART_SALE_UNIT_SWITCHED, $this->listener);
        $this->getService(SaleUnitContext::class)->reset();
        $this->getService(SaleUnitActivityChecker::class)->reset();
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testMovingToAnotherUnitRemovesReducesAndReprices(): void
    {
        $gone = $this->product();
        $short = $this->product();
        $repriced = $this->product();
        $kept = $this->product();
        $cart = $this->cartOn($this->first);
        $this->line($cart, $gone, 2);
        $this->line($cart, $short, 3);
        $this->line($cart, $repriced, 1);
        $this->line($cart, $kept, 1);

        // The second unit has none of the first product left, one of the second,
        // and its own price for the third; the fourth it sells from the national stock.
        $this->factory->saleUnitProductSaleElements($this->second, $gone->getDefaultSaleElements(), 0.0);
        $this->factory->saleUnitProductSaleElements($this->second, $short->getDefaultSaleElements(), 1.0);
        $this->factory->saleUnitProductPrice($this->second, $repriced->getDefaultSaleElements(), $this->currency, 7.5);

        $this->buyFrom($this->second);
        $this->follow($cart);

        self::assertSame($this->second->getId(), $this->reloaded($cart)->getSaleUnitId());
        self::assertNull($this->lineOf($cart, $gone), 'The unit has none left: the line goes.');
        self::assertSame(1.0, (float) $this->lineOf($cart, $short)?->getQuantity(), 'The line shrinks to what the unit holds.');
        self::assertEqualsWithDelta(7.5, $this->lineOf($cart, $repriced)?->getRealPrice(), 0.000001, 'The line takes the unit price.');
        self::assertSame(1.0, (float) $this->lineOf($cart, $kept)?->getQuantity());

        self::assertCount(1, $this->switches);
        $switch = $this->switches[0];
        self::assertSame($this->first->getId(), $switch->getPreviousSaleUnitId());
        self::assertSame($this->second->getId(), $switch->getSaleUnitId());
        self::assertSame([$gone->getRef()], array_map(static fn ($change) => $change->productRef, $switch->getRemovedLines()));
        self::assertSame([$short->getRef()], array_map(static fn ($change) => $change->productRef, $switch->getReducedLines()));
        self::assertSame([$repriced->getRef()], array_map(static fn ($change) => $change->productRef, $switch->getRepricedLines()));

        $summary = $this->session()->getFlashBag()->get(CartSaleUnitSwitchedEvent::FLASH_KEY);
        self::assertCount(1, $summary, 'The theme finds one summary in the flash bag.');
        self::assertSame($this->second->getId(), $summary[0]['sale_unit_id']);
        self::assertSame(2.0, $summary[0]['removed'][0]['previous_quantity']);
        self::assertSame(1.0, $summary[0]['reduced'][0]['quantity']);
        self::assertEqualsWithDelta(7.5, $summary[0]['repriced'][0]['price'], 0.000001);
    }

    public function testComingBackToNationalGivesTheNationalPriceBack(): void
    {
        $product = $this->product();
        $this->factory->saleUnitProductPrice($this->first, $product->getDefaultSaleElements(), $this->currency, 7.5);
        $cart = $this->cartOn($this->first);
        $this->buyFrom($this->first);
        $this->addThroughTheCart($cart, $product, 1);
        self::assertEqualsWithDelta(7.5, $this->lineOf($cart, $product)?->getRealPrice(), 0.000001);

        $this->getService(SaleUnitContext::class)->setSaleUnit(null);
        $this->follow($cart);

        self::assertNull($this->reloaded($cart)->getSaleUnitId());
        self::assertEqualsWithDelta(10.0, $this->lineOf($cart, $product)?->getRealPrice(), 0.000001);
        self::assertCount(1, $this->switches);
        self::assertCount(1, $this->switches[0]->getRepricedLines());
    }

    public function testACartAlreadyOnTheUnitIsLeftAloneWithoutAQuery(): void
    {
        $cart = $this->cartOn($this->first);
        $this->line($cart, $this->product(), 2);
        $this->buyFrom($this->first);

        $statements = $this->recordSqlQueries(fn () => $this->follow($cart));

        self::assertSame([], $statements);
        self::assertSame([], $this->switches);
    }

    public function testASingleShopCartIsLeftAloneWithoutAQuery(): void
    {
        $cart = $this->factory->cart(null, ['currency' => $this->currency]);
        $this->line($cart, $this->product(), 2);

        $statements = $this->recordSqlQueries(fn () => $this->follow($cart));

        self::assertSame([], $statements);
        self::assertSame([], $this->switches);
        self::assertNull($this->reloaded($cart)->getSaleUnitId());
    }

    public function testAMoveThatChangesNothingStillMovesTheCartButLeavesNoMessage(): void
    {
        $cart = $this->cartOn($this->first);
        $this->line($cart, $this->product(), 1);
        $this->buyFrom($this->second);

        $this->follow($cart);

        self::assertSame($this->second->getId(), $this->reloaded($cart)->getSaleUnitId());
        self::assertCount(1, $this->switches);
        self::assertFalse($this->switches[0]->hasChanges());
        self::assertSame([], $this->session()->getFlashBag()->get(CartSaleUnitSwitchedEvent::FLASH_KEY));
    }

    public function testANewCartIsBornOnTheCurrentUnit(): void
    {
        $this->buyFrom($this->first);

        $event = $this->dispatch(new CartCreateEvent(), TheliaEvents::CART_CREATE_NEW);

        self::assertSame($this->first->getId(), $event->getCart()?->getSaleUnitId());
    }

    public function testTheSessionHandsOutTheCartCheckedForTheCurrentUnit(): void
    {
        $product = $this->product();
        $cart = $this->cartOn($this->first);
        $this->line($cart, $product, 3);
        $this->factory->saleUnitProductSaleElements($this->second, $product->getDefaultSaleElements(), 2.0);
        $this->session()->setSessionCart($cart);
        $this->buyFrom($this->second);

        $sessionCart = $this->session()->getSessionCart($this->dispatcher);

        self::assertSame($cart->getId(), $sessionCart->getId());
        self::assertSame($this->second->getId(), $sessionCart->getSaleUnitId());
        self::assertSame(2.0, (float) $this->lineOf($cart, $product)?->getQuantity());
    }

    public function testACartRestoredFromItsCookieIsCheckedForTheCurrentUnitToo(): void
    {
        $product = $this->product();
        $cart = $this->cartOn($this->first);
        $this->line($cart, $product, 3);
        $this->factory->saleUnitProductSaleElements($this->second, $product->getDefaultSaleElements(), 2.0);
        $this->getService(RequestStack::class)->getMainRequest()?->cookies->set(
            ConfigQuery::read('cart.cookie_name', 'thelia_cart'),
            $cart->getToken(),
        );
        $this->buyFrom($this->second);

        $sessionCart = $this->session()->getSessionCart($this->dispatcher);

        self::assertSame($cart->getId(), $sessionCart->getId());
        self::assertSame($this->second->getId(), $sessionCart->getSaleUnitId());
        self::assertSame(2.0, (float) $this->lineOf($cart, $product)?->getQuantity());
        self::assertCount(1, $this->switches, 'The restored cart is reported like any other.');
        self::assertCount(1, $this->switches[0]->getReducedLines());
    }

    /**
     * Two requests of the same visitor may both find the cart on the old unit: the
     * one that moves it second must not check it again against what the first one
     * already settled.
     */
    public function testACartAnotherRequestAlreadyMovedIsLeftAlone(): void
    {
        $product = $this->product();
        $cart = $this->cartOn($this->first);
        $this->line($cart, $product, 3);
        $this->factory->saleUnitProductSaleElements($this->second, $product->getDefaultSaleElements(), 1.0);
        // Another request moved it meanwhile; this one still holds the cart as it read it.
        CartQuery::create()->filterById($cart->getId())->update(['SaleUnitId' => $this->second->getId()], $this->getPropelConnection());
        $this->buyFrom($this->second);

        $this->follow($cart);

        self::assertSame([], $this->switches, 'The move belongs to the request that made it.');
        self::assertSame(3.0, (float) $this->lineOf($cart, $product)?->getQuantity());
    }

    public function testTheDiscountIsComputedAgainAfterAMove(): void
    {
        $cart = $this->cartOn($this->first);
        $this->line($cart, $this->product(), 1);
        $cart->setDiscount('5.000000')->save($this->getPropelConnection());
        self::assertEqualsWithDelta(5.0, (float) $this->reloaded($cart)->getDiscount(), 0.000001);
        $this->session()->start();
        $this->session()->setSessionCart($cart);
        $this->buyFrom($this->second);

        $this->session()->getSessionCart($this->dispatcher);

        self::assertCount(1, $this->switches);
        self::assertEqualsWithDelta(0.0, (float) $this->reloaded($cart)->getDiscount(), 0.000001, 'No coupon applies: the stale discount is gone.');
    }

    public function testTheBackOfficeLeavesTheCartAlone(): void
    {
        $cart = $this->cartOn($this->first);
        $this->line($cart, $this->product(), 1);
        $this->buyFrom($this->second);
        \Thelia\Core\HttpFoundation\Request::$isAdminEnv = true;

        $this->follow($cart);

        self::assertSame($this->first->getId(), $this->reloaded($cart)->getSaleUnitId());
        self::assertSame([], $this->switches);
    }

    private function product(): Product
    {
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->currency,
            ['baseQuantity' => 100, 'basePrice' => 10.0],
        );
        // The fixture keeps the product cached on the sale element with no
        // reference: the switch reads it back by id.
        $product->getDefaultSaleElements();

        return $product;
    }

    private function cartOn(SaleUnit $saleUnit): Cart
    {
        $cart = $this->factory->cart(null, ['currency' => $this->currency]);
        $cart->setSaleUnitId($saleUnit->getId())->save($this->getPropelConnection());

        return $cart;
    }

    private function line(Cart $cart, Product $product, float $quantity): void
    {
        $this->factory->cartItem($cart, $product, $product->getDefaultSaleElements(), [
            'quantity' => $quantity,
            'price' => '10.000000',
            'promoPrice' => '10.000000',
        ]);
    }

    private function addThroughTheCart(Cart $cart, Product $product, int $quantity): void
    {
        $event = new CartEvent($cart);
        $event
            ->setProductId($product->getId())
            ->setProductSaleElementsId($product->getDefaultSaleElements()->getId())
            ->setQuantity($quantity)
            ->setNewness(true)
            ->setAppend(true);

        $this->dispatch($event, TheliaEvents::CART_ADDITEM);
    }

    private function buyFrom(SaleUnit $saleUnit): void
    {
        $this->getService(SaleUnitContext::class)->setSaleUnit($saleUnit);
    }

    private function follow(Cart $cart): void
    {
        $this->dispatch(new CartEvent($cart), TheliaEvents::CART_FOLLOW_SALE_UNIT);
    }

    private function reloaded(Cart $cart): Cart
    {
        CartTableMap::clearInstancePool();

        return CartQuery::create()->findPk($cart->getId(), $this->getPropelConnection())
            ?? throw new \LogicException('The cart is gone.');
    }

    private function lineOf(Cart $cart, Product $product): ?\Thelia\Model\CartItem
    {
        CartItemTableMap::clearInstancePool();

        return CartItemQuery::create()
            ->filterByCartId($cart->getId())
            ->filterByProductSaleElementsId($product->getDefaultSaleElements()->getId())
            ->findOne($this->getPropelConnection());
    }

    private function session(): Session
    {
        $session = $this->getService(RequestStack::class)->getMainRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }
}
