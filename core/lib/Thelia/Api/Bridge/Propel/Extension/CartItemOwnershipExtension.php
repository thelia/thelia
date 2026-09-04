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

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Resource\CartItem;
use Thelia\Api\Security\CartOwnership;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Map\CartItemTableMap;

/**
 * Scopes the front cart item endpoints to the caller's own cart.
 *
 * A cart item is reached by a sequential numeric id, and the front operations
 * carry no access rule of their own: without this, any id is enough to read,
 * change or drop a line of somebody else's cart. Filtering in the query means
 * the collection, the item read, and the read step of PUT and DELETE are all
 * bounded by the same rule.
 *
 * The admin endpoints are left alone: they sit behind ROLE_ADMIN and a
 * back-office user legitimately reads every cart.
 */
final readonly class CartItemOwnershipExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private CartOwnership $cartOwnership,
    ) {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToOwnedCart($query, $resourceClass, $operation);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToOwnedCart($query, $resourceClass, $operation);
    }

    private function scopeToOwnedCart(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (CartItem::class !== $resourceClass || !$query instanceof CartItemQuery) {
            return;
        }

        if (!str_starts_with((string) $operation?->getUriTemplate(), '/front/')) {
            return;
        }

        // A guest is scoped to the one cart its token names, never to the customer row
        // it authenticates as: the shop reuses the guest account behind an address, so
        // that row is shared with every earlier visitor who ordered from the same
        // address, and their baskets hang off it too. A guest token naming no cart owns
        // no line, which is what the criterion below says.
        if ($this->cartOwnership->isGuest()) {
            $claimedCartId = $this->cartOwnership->guestCartId();

            if (null !== $claimedCartId) {
                $query->filterByCartId($claimedCartId);

                return;
            }

            $query->add(CartItemTableMap::COL_CART_ID, null, Criteria::ISNULL);

            return;
        }

        $customerId = $this->cartOwnership->customerId();

        if (null !== $customerId) {
            $query->useCartQuery()
                ->filterByCustomerId($customerId)
                ->endUse();

            return;
        }

        $sessionCartId = $this->cartOwnership->sessionCartId();

        if (null !== $sessionCartId) {
            $query->filterByCartId($sessionCartId);

            return;
        }

        // Nobody owns a cart here, so nothing is in scope. cart_id is NOT NULL,
        // which makes this criterion match no row at all.
        $query->add(CartItemTableMap::COL_CART_ID, null, Criteria::ISNULL);
    }
}
