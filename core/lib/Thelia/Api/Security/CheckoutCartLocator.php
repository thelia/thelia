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

namespace Thelia\Api\Security;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;

/**
 * The cart a checkout operation acts on, once it is established it is the caller's.
 *
 * The checkout operations read `{cartId}` with `read: false`, so no object ever reaches
 * a `security:` expression and the ownership check has to happen here. It answers by
 * throwing, and it throws the same thing whatever went wrong: a cart that does not
 * exist, a cart of somebody else and a cart reached with a guest token are one answer,
 * word for word. Telling them apart would turn six endpoints into a way of counting the
 * carts of the shop and of learning which ids are taken.
 */
final readonly class CheckoutCartLocator
{
    /**
     * The one sentence every refusal of this kind comes back with. A constant rather
     * than three literals, so that a reworded message cannot start telling the cases
     * apart again.
     */
    public const NOT_FOUND_MESSAGE = 'No such cart.';

    public function __construct(
        private CartOwnership $cartOwnership,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     *
     * @throws NotFoundHttpException when the cart is not one the authenticated account may check out
     * @throws PropelException
     */
    public function ownedCart(array $uriVariables): Cart
    {
        $cartId = (int) ($uriVariables['cartId'] ?? 0);

        if ($cartId <= 0) {
            throw new NotFoundHttpException(self::NOT_FOUND_MESSAGE);
        }

        // Checking out without an account is another story, told by another set of
        // operations. Today a guest token is pinned to ROLE_GUEST, which implies nothing,
        // so the `is_granted("ROLE_CUSTOMER")` on every operation turns it away first and
        // this never fires. It stays because the ownership rule the cart endpoints share
        // with this one does let a guest through — it is written for the guest tunnel —
        // and a role hierarchy gaining one line would otherwise hand a guest the checkout
        // of an account.
        if ($this->cartOwnership->isGuest()) {
            throw new NotFoundHttpException(self::NOT_FOUND_MESSAGE);
        }

        if (null === $this->cartOwnership->customerId()) {
            throw new NotFoundHttpException(self::NOT_FOUND_MESSAGE);
        }

        $cart = CartQuery::create()->findPk($cartId);

        if (!$cart instanceof Cart || !$this->cartOwnership->ownsCart($cart->getCustomerId(), $cart->getId())) {
            throw new NotFoundHttpException(self::NOT_FOUND_MESSAGE);
        }

        return $cart;
    }
}
