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
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\Customer;

class CartRetriever
{
    public function __construct(
        protected RequestStack $requestStack,
        protected EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws PropelException
     */
    public function fromSessionOrCreateNew(
        Customer $customer,
    ): Cart {
        $cart = $this->fromSession();

        if (null === $cart) {
            $cart = new Cart();
            $cart->setCustomerId($customer->getId());
            $cart->save();
        }

        return $cart;
    }

    public function fromSession(): ?Cart
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();

        if (!$session instanceof Session) {
            Tlog::getInstance()->error(
                'Failed to get cart event : no session available in the current request.'
            );

            return null;
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
