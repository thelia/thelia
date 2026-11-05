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

namespace Thelia\Domain\Catalog\Stock;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Cart\CartDuplicationEvent;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Cart\CartSaleUnitSwitchedEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Holds the stock of a cart again once it changed, after the action that changed it: a line
 * added, a quantity changed, or the cart moved to another sale unit.
 * What is held follows the scope of the shop (StockReservationService::holdAfterCartChange()).
 *
 * A line removed, or a cart emptied, holds nothing without any of this: its row is gone. The copy
 * of a cart made at sign-in is held once the cart it copies is deleted, which released what that
 * one held: a shop that reserves from the cart on keeps the buyer's units through the sign-in.
 */
final readonly class StockReservationCartListener
{
    public function __construct(private StockReservationService $stockReservationService)
    {
    }

    #[AsEventListener(event: TheliaEvents::CART_ADDITEM, priority: 0)]
    #[AsEventListener(event: TheliaEvents::CART_UPDATEITEM, priority: 0)]
    public function afterLineChange(CartEvent $event): void
    {
        $this->stockReservationService->holdAfterCartChange($event->getCart());
    }

    #[AsEventListener(event: TheliaEvents::CART_SALE_UNIT_SWITCHED, priority: 0)]
    public function afterSaleUnitSwitch(CartSaleUnitSwitchedEvent $event): void
    {
        $this->stockReservationService->holdAfterCartChange($event->getCart());
    }

    /** After the copy at sign-in, the cart it copies being deleted already (Model\Cart::duplicate()). */
    #[AsEventListener(event: TheliaEvents::CART_DUPLICATE, priority: -128)]
    public function afterCopy(CartDuplicationEvent $event): void
    {
        $this->stockReservationService->holdAfterCartChange($event->getDuplicatedCart());
    }
}
