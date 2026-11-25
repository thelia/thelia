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

namespace Thelia\Tests\Integration\Quote\Cart;

use Thelia\Action\Cart as CartAction;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Cart\DTO\CartItemDeleteDTO;
use Thelia\Domain\Cart\DTO\CartItemUpdateQuantityDTO;
use Thelia\Domain\Cart\Service\CartItemService;
use Thelia\Domain\Quote\Cart\QuoteCartLockedException;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;

/**
 * What the buyer holds in the cart of a quote is what the shop agreed to sell: no line is added,
 * removed or given another quantity, by the services of the cart or by the events they send.
 */
final class QuoteCartLinesLockedTest extends QuoteCartTestCase
{
    public function testTheServicesRefuseToAddARemoveOrChangeALineBeforeAnythingHearsOfIt(): void
    {
        $quoteCart = $this->quoteCart();
        $service = $this->getService(CartItemService::class);
        $other = $this->product(['basePrice' => 10.0]);
        $heard = [];

        foreach ([TheliaEvents::CART_ADDITEM, TheliaEvents::CART_DELETEITEM, TheliaEvents::CART_UPDATEITEM] as $eventName) {
            $this->dispatcher->addListener($eventName, static function () use (&$heard, $eventName): void {
                $heard[] = $eventName;
            }, 1024);
        }

        foreach ([
            'add' => fn () => $service->addItem(new CartItemAddDTO($quoteCart, $other->getId(), $this->defaultPseFor($other)->getId(), 1.0)),
            'delete' => fn () => $service->deleteItem(new CartItemDeleteDTO($quoteCart, $this->quoteLine->getId())),
            'quantity' => fn () => $service->updateQuantityItem(new CartItemUpdateQuantityDTO($quoteCart, $this->quoteLine->getId(), 3.0)),
        ] as $name => $change) {
            try {
                $change();
                self::fail('The '.$name.' of a line of a quote was accepted.');
            } catch (QuoteCartLockedException $refusal) {
                self::assertSame(QuoteCartLockedException::LINES_FROZEN, $refusal->reason(), $name);
                self::assertSame('The lines of a quote cannot be changed: ask the shop for another quote.', $refusal->getMessage());
            }
        }

        self::assertSame([], $heard, 'the refusal comes before the event');
        $this->assertTheCartIsAsItWas($quoteCart);
    }

    public function testTheCartActionRefusesWhateverTheEventComesFrom(): void
    {
        $quoteCart = $this->quoteCart();
        $other = $this->product(['basePrice' => 10.0]);

        $add = (new CartEvent($quoteCart))
            ->setProductId($other->getId())
            ->setProductSaleElementsId($this->defaultPseFor($other)->getId())
            ->setQuantity(1)
            ->setNewness(true)
            ->setAppend(false);
        $delete = (new CartEvent($quoteCart))->setCartItemId($this->quoteLine->getId());
        $update = (new CartEvent($quoteCart))->setCartItemId($this->quoteLine->getId())->setQuantity(3.0);

        foreach ([TheliaEvents::CART_ADDITEM => $add, TheliaEvents::CART_DELETEITEM => $delete, TheliaEvents::CART_UPDATEITEM => $update] as $eventName => $event) {
            try {
                $this->dispatch($event, $eventName);
                self::fail($eventName.' went through on the cart of a quote.');
            } catch (QuoteCartLockedException $refusal) {
                self::assertSame(QuoteCartLockedException::LINES_FROZEN, $refusal->reason(), $eventName);
            }
        }

        // The action itself, called without the dispatcher.
        $this->expectException(QuoteCartLockedException::class);
        $this->getService(CartAction::class)->deleteItem($delete);
    }

    public function testTheFacadeRefusesToEmptyTheLines(): void
    {
        $quoteCart = $this->quoteCart();

        try {
            $this->getService(CartFacade::class)->reset(true);
            self::fail('The lines of a quote were emptied.');
        } catch (QuoteCartLockedException) {
        }

        $this->assertTheCartIsAsItWas($quoteCart);
    }

    public function testAnOrdinaryCartIsChangedAsEver(): void
    {
        $ordinaryCart = $this->ordinaryCart();
        $line = $this->lineOf($ordinaryCart);
        $service = $this->getService(CartItemService::class);

        $service->updateQuantityItem(new CartItemUpdateQuantityDTO($ordinaryCart, $line->getId(), 3.0));
        self::assertSame(3.0, (float) $this->lineOf($ordinaryCart)->getQuantity());

        $service->deleteItem(new CartItemDeleteDTO($ordinaryCart, $line->getId()));
        self::assertSame(0, CartItemQuery::create()->filterByCartId($ordinaryCart->getId())->count());
    }

    private function assertTheCartIsAsItWas(Cart $quoteCart): void
    {
        $lines = CartItemQuery::create()->filterByCartId($quoteCart->getId())->find();

        self::assertCount(1, $lines);
        self::assertSame($this->quoteLine->getId(), $lines->getFirst()->getId());
        self::assertSame(12.0, (float) $lines->getFirst()->getQuantity());
        self::assertNotNull(CartQuery::create()->findPk($quoteCart->getId()));
    }
}
