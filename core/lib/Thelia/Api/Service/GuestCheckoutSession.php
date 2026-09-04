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

namespace Thelia\Api\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Cart;
use Thelia\Model\Customer;

/**
 * The cart a guest checkout is about, and what happens to it once the guest exists.
 *
 * The front cart is held by the session — that is what GET /api/front/cart reads and
 * what the ownership rules of the cart endpoints are written against — so the guest
 * registration takes its cart from there too. It is never taken from the request body:
 * a caller naming a cart id would be naming somebody else's cart, and would be handed
 * a token for it.
 */
final readonly class GuestCheckoutSession
{
    public function __construct(
        private RequestStack $requestStack,
        private Session $session,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * The cart this visitor is checking out, persisted so that it can be named.
     *
     * A cart restored from the session may never have been written — Thelia keeps a
     * brand new one in memory until something is added to it. A guest token names its
     * cart by id, so the cart has to exist before the token is issued.
     */
    public function currentCart(): Cart
    {
        $request = $this->requestStack->getMainRequest();

        if (null === $request) {
            throw new \LogicException('A guest checkout needs a request to read the current cart from.');
        }

        $request->setSession($this->session);

        $cart = $this->session->getSessionCart($this->eventDispatcher);

        if ($cart->isNew()) {
            $cart->save();
            $this->session->setSessionCart($cart);
        }

        return $cart;
    }

    /**
     * Hand the cart to the guest account, in the database and nowhere else.
     *
     * Returns the cart the token may name, or null when the session is holding a cart
     * that already belongs to someone else: a registration must never become a way to
     * be handed a token for another customer's cart.
     *
     * Nothing is written into the HTTP session. The api firewall is stateless and the
     * JWT carries the identity, so a session customer set from here would be an identity
     * the API never reads and the front office would: one call to POST
     * /api/front/guest-customers would have signed the caller's browser in as a guest,
     * outside of any checkout the browser was going through. The response carries the
     * cart id along with the token, and that pair is what the caller goes on with.
     */
    public function attachToGuest(Cart $cart, Customer $guest): ?Cart
    {
        $ownerId = $cart->getCustomerId();

        if (null !== $ownerId && $ownerId !== $guest->getId()) {
            return null;
        }

        if (null === $ownerId) {
            $cart->setCustomerId($guest->getId())->save();
        }

        return $cart;
    }
}
