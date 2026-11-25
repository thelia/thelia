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

namespace Thelia\Domain\Quote\Cart;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Cart\CartCheckoutEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Exception\QuoteDeliveryImposedException;

/**
 * Before anything writes the carrier or the delivery address of a cart, the cart of a quote says
 * whether it accepts them: it ships the way the quote says (see {@see QuoteCartDeliveryGuard}).
 *
 * Above the listeners that write the choice (128) and those that read it (10), so that a refused
 * choice leaves the cart, its postage and its discount untouched.
 */
final readonly class QuoteCartDeliverySubscriber implements EventSubscriberInterface
{
    public const int PRIORITY = 200;

    public function __construct(private QuoteCartDeliveryGuard $guard)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CART_SET_DELIVERY_MODULE => ['refuseAnotherCarrier', self::PRIORITY],
            TheliaEvents::CART_SET_DELIVERY_ADDRESS => ['refuseAnotherAddress', self::PRIORITY],
            TheliaEvents::CART_SET_DELIVERY_ADDRESS_MANUAL => ['refuseAnotherTypedAddress', self::PRIORITY],
        ];
    }

    /**
     * @throws QuoteDeliveryImposedException
     */
    public function refuseAnotherCarrier(CartCheckoutEvent $event): void
    {
        $this->guard->refuseAnotherCarrier($event->getCart(), $event->getDeliveryModuleId());
    }

    /**
     * @throws QuoteDeliveryImposedException
     */
    public function refuseAnotherAddress(CartCheckoutEvent $event): void
    {
        $this->guard->refuseAnotherAddress($event->getCart(), $event->getDeliveryAddressId());
    }

    /**
     * @throws QuoteDeliveryImposedException
     */
    public function refuseAnotherTypedAddress(CartCheckoutEvent $event): void
    {
        $this->guard->refuseAnotherTypedAddress($event->getCart(), $event->getCartAddress());
    }
}
