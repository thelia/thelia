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

namespace Thelia\Core\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Thelia\Core\Event\Cart\CartSaleUnitSwitchedEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Leaves the theme a summary of what a sale unit switch changed in the cart, in the
 * session flash bag under CartSaleUnitSwitchedEvent::FLASH_KEY, so the next page
 * can tell the visitor which lines went, shrank or changed price. Nothing is
 * written when nothing changed.
 */
final readonly class CartSaleUnitSwitchFlashListener
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    #[AsEventListener(event: TheliaEvents::CART_SALE_UNIT_SWITCHED, priority: 0)]
    public function __invoke(CartSaleUnitSwitchedEvent $event): void
    {
        if (!$event->hasChanges()) {
            return;
        }

        $request = $this->requestStack->getMainRequest();

        if (null === $request || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        if (!$session instanceof FlashBagAwareSessionInterface) {
            return;
        }

        $session->getFlashBag()->add(CartSaleUnitSwitchedEvent::FLASH_KEY, $event->toSummary());
    }
}
