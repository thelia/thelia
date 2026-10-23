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

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Api\Resource\CustomerPaymentMethod;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Customer;
use Thelia\Model\CustomerPaymentMethodQuery;
use Thelia\Model\Map\CustomerPaymentMethodTableMap;

/**
 * Scopes the front stored payment method endpoints to the caller's own methods.
 *
 * A stored payment method is reached by a sequential numeric id, and the
 * `security:` expression of its front operations only asks for a customer, not
 * for the owner: filtering in the query means the collection, the item read, the
 * PATCH read step and the DELETE read step are all bounded by the same rule, and
 * another customer's id reads as "not found" rather than "forbidden", the same
 * answer a missing id gives, so probing sequential ids learns nothing about which
 * ones exist.
 *
 * Who the caller is comes from the firewall token when there is one. A front page
 * has no token, because its customer lives in the Thelia session, so an in-process
 * read (`resources()`, `DataAccessService`) falls back to the session customer. That
 * fallback applies only when the main request already holds its session object
 * (`hasSession(true)`, the rule of CartOwnership): a session still behind the lazy
 * factory of the request is not created here, and in CLI there is none. The customer
 * is then read through SecurityContext. A token that names someone
 * other than a customer, an administrator on a front route for instance, is never
 * replaced by the session: it keeps reading nothing. A guest in the session owns no
 * stored method either.
 *
 * The admin endpoints are left alone: they sit behind ROLE_ADMIN and a
 * back-office user legitimately reads every customer's stored methods.
 */
final readonly class CustomerPaymentMethodOwnershipExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
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
        if (CustomerPaymentMethod::class !== $resourceClass || !$query instanceof CustomerPaymentMethodQuery) {
            return;
        }

        if (!$operation instanceof HttpOperation || !str_starts_with((string) $operation->getUriTemplate(), '/front/')) {
            return;
        }

        $customer = $this->caller();

        if (!$customer instanceof Customer) {
            // Nobody authenticated as a customer here, so nothing is in scope.
            // customer_id is NOT NULL, which makes this criterion match no row.
            $query->add(CustomerPaymentMethodTableMap::COL_CUSTOMER_ID, null, Criteria::ISNULL);

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

        // Only a session the request already holds is read, the rule of CartOwnership:
        // SecurityContext would create it from the request's lazy factory, and a read
        // that looks for a customer must not be what opens a session.
        if (!$this->requestStack->getMainRequest()?->hasSession(true)) {
            return null;
        }

        $sessionCustomer = $this->securityContext->getCustomerUser();

        return $sessionCustomer instanceof Customer && !$sessionCustomer->isGuest() ? $sessionCustomer : null;
    }
}
