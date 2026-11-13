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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Api\Bridge\Propel\FrontOperation;
use Thelia\Api\Resource\Subscription;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Customer;
use Thelia\Model\Map\SubscriptionTableMap;
use Thelia\Model\SubscriptionQuery;

/**
 * Scopes the front subscription endpoints to the caller's own subscriptions, as
 * CustomerPaymentMethodOwnershipExtension does for stored payment methods: the collection,
 * the item read and the read step of every action are bounded by the same rule, and another
 * customer's id reads as "not found", the answer a missing id gives.
 *
 * The caller is the customer of the firewall token, or, in-process, the signed-in customer of
 * the session when the request already holds one. A token of an administrator, a guest customer
 * or nobody reads nothing. The admin endpoints are left alone.
 */
final readonly class SubscriptionOwnershipExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private SecurityContext $securityContext,
        private RequestStack $requestStack,
    ) {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToOwnedCustomer($query, $resourceClass, $operation);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scopeToOwnedCustomer($query, $resourceClass, $operation);
    }

    private function scopeToOwnedCustomer(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (Subscription::class !== $resourceClass || !$query instanceof SubscriptionQuery || !FrontOperation::isFront($operation)) {
            return;
        }

        $customer = $this->caller();

        if (!$customer instanceof Customer || $customer->isGuest()) {
            // customer_id is NOT NULL: this criterion matches no row.
            $query->add(SubscriptionTableMap::COL_CUSTOMER_ID, null, Criteria::ISNULL);

            return;
        }

        $query->filterByCustomerId($customer->getId());
    }

    private function caller(): mixed
    {
        $token = $this->tokenStorage->getToken();

        if (null !== $token) {
            return $token->getUser();
        }

        if (!$this->requestStack->getMainRequest()?->hasSession(true)) {
            return null;
        }

        return $this->securityContext->getCustomerUser();
    }
}
