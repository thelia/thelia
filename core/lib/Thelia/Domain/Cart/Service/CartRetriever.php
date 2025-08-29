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

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Cart\CartPersistEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Customer\Service\CustomerContext;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;

class CartRetriever
{
    public function __construct(
        protected RequestStack $requestStack,
        protected EventDispatcherInterface $eventDispatcher,
        protected CustomerContext $customerContext,
        protected CartContext $cartContext,
    ) {
    }

    /**
     * @throws PropelException
     */
    public function fromSessionOrCreateNew(): Cart
    {
        $cart = $this->fromSession();
        if (null === $cart?->getId()) {
            $cartPersistEvent = new CartPersistEvent($cart);
            $this->eventDispatcher->dispatch($cartPersistEvent, TheliaEvents::CART_PERSIST);
        }

        return $cart;
    }

    public function fromSession(): Cart
    {
        $session = $this->requestStack->getMainRequest()?->getSession();

        if (!$session instanceof Session) {
            throw new \LogicException('Failed to get cart event : no session available in the current request.');
        }

        return $session->getSessionCart($this->eventDispatcher);
    }

    public function fromId(int $cartId): ?Cart
    {
        $cart = CartQuery::create()->findPk($cartId);

        if (null === $cart) {
            Tlog::getInstance()->error(
                \sprintf('Failed to get cart event : no cart found with id %d.', $cartId)
            );

            return null;
        }

        return $cart;
    }
}
