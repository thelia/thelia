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

namespace Thelia\Domain\Cart\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Cart;

class CartContext
{
    public function __construct(
        protected RequestStack $requestStack,
        protected EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function clearCartSession(): void
    {
        $session = $this->requestStack->getMainRequest()?->getSession();
        if ($session instanceof Session) {
            $session->clearSessionCart($this->eventDispatcher);
        }
    }

    public function addCartSession(Cart $cart): void
    {
        $session = $this->requestStack->getMainRequest()?->getSession();
        if ($session instanceof Session) {
            $session->setSessionCart($cart);
        }
    }
}
