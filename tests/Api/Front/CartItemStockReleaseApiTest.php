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

namespace Thelia\Tests\Api\Front;

use Thelia\Core\Event\ProductSaleElement\StockReservationEvent;
use Thelia\Core\Event\ProductSaleElement\StockReservationLine;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Model\CartItem;
use Thelia\Model\ConfigQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * A line removed through the front API releases the stock it held, and the release is told once
 * the transaction of the write is committed.
 */
final class CartItemStockReleaseApiTest extends ApiTestCase
{
    /** @var list<array{0: int, 1: list<StockReservationLine>}> */
    private array $released = [];

    /** @var list<array{0: int, 1: list<StockReservationLine>}> */
    private array $reserved = [];

    private ?\Closure $listener = null;

    private ?\Closure $reservedListener = null;

    /** @var array<string, string|null> */
    private array $settingsBefore = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['check-available-stock', 'allow_negative_stock', StockSettings::RESERVATION_ENABLED, StockSettings::RESERVATION_SCOPE, StockSettings::RESERVATION_DURATION] as $name) {
            $this->settingsBefore[$name] = ConfigQuery::create()->findOneByName($name)?->getValue();
        }

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'checkout');
        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '15');
        ConfigQuery::resetCache();
        // The transaction this test wraps itself in is not one of a caller.
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn($this->getPropelConnection());

        $this->listener = function (StockReservationEvent $event): void {
            $this->released[] = [$event->getCartId(), $event->getLines()];
        };
        $this->getService('event_dispatcher')->addListener(TheliaEvents::STOCK_RELEASED, $this->listener);

        $this->reservedListener = function (StockReservationEvent $event): void {
            $this->reserved[] = [$event->getCartId(), $event->getLines()];
        };
        $this->getService('event_dispatcher')->addListener(TheliaEvents::STOCK_RESERVED, $this->reservedListener);
    }

    protected function tearDown(): void
    {
        if (null !== $this->listener) {
            $this->getService('event_dispatcher')->removeListener(TheliaEvents::STOCK_RELEASED, $this->listener);
        }

        if (null !== $this->reservedListener) {
            $this->getService('event_dispatcher')->removeListener(TheliaEvents::STOCK_RESERVED, $this->reservedListener);
        }

        foreach ($this->settingsBefore as $name => $value) {
            ConfigQuery::write($name, $value ?? '');
        }

        ConfigQuery::resetCache();
        $this->getService(TestCallerTransaction::class)->reset();

        parent::tearDown();
    }

    public function testRemovingAHeldLineTellsItReleasedOnceTheWriteIsCommitted(): void
    {
        [$customer, $cart, $line] = $this->cartLine();
        $this->getService(CheckoutFacade::class)->holdStock($cart);

        $response = $this->jsonRequest('DELETE', '/api/front/cart_items/'.$line->getId(), token: $this->authenticateAsCustomer($customer));

        self::assertSame(204, $response->getStatusCode(), (string) $response->getContent());
        self::assertEquals([[(int) $cart->getId(), [new StockReservationLine((int) $line->getId(), (int) $line->getProductSaleElementsId(), 2.0)]]], $this->released);
    }

    public function testRemovingALineThatHeldNothingTellsNothing(): void
    {
        [$customer, , $line] = $this->cartLine();

        $response = $this->jsonRequest('DELETE', '/api/front/cart_items/'.$line->getId(), token: $this->authenticateAsCustomer($customer));

        self::assertSame(204, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([], $this->released);
    }

    /**
     * The quantity of a held line goes from 2 to 5 inside the transaction of the write; the hold that
     * follows its commit tells 2 released, then 5 reserved. Releasing the cart later tells 5.
     */
    public function testChangingTheQuantityOfAHeldLineTellsTheOldQuantityReleasedThenTheNewOneReserved(): void
    {
        [$customer, $cart, $line] = $this->cartLine();
        $token = $this->authenticateAsCustomer($customer);
        $this->getService(CheckoutFacade::class)->holdStock($cart);
        $this->released = $this->reserved = [];

        $response = $this->jsonRequest('PUT', '/api/front/cart_items/'.$line->getId(), ['quantity' => 5], $token);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertEquals([[(int) $cart->getId(), [new StockReservationLine((int) $line->getId(), (int) $line->getProductSaleElementsId(), 2.0)]]], $this->released);
        self::assertEquals([[(int) $cart->getId(), [new StockReservationLine((int) $line->getId(), (int) $line->getProductSaleElementsId(), 5.0)]]], $this->reserved);

        $this->released = [];
        $response = $this->jsonRequest('DELETE', '/api/front/cart_items/'.$line->getId(), token: $token);

        self::assertSame(204, $response->getStatusCode(), (string) $response->getContent());
        self::assertEquals([[(int) $cart->getId(), [new StockReservationLine((int) $line->getId(), (int) $line->getProductSaleElementsId(), 5.0)]]], $this->released);
    }

    /**
     * @return array{0: \Thelia\Model\Customer, 1: \Thelia\Model\Cart, 2: CartItem}
     */
    private function cartLine(): array
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['baseQuantity' => 10]);

        return [$customer, $cart, $factory->cartItem($cart, $product, null, ['quantity' => 2.0])];
    }
}
