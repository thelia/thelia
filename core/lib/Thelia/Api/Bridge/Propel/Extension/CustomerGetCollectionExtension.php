<?php

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
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\AccessMapInterface;
use Thelia\Model\Customer;

final class CustomerGetCollectionExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private readonly TokenStorageInterface $token,
        private readonly RequestStack $requestStack,
        #[Autowire(service: 'security.access_map')]
        private readonly AccessMapInterface $accessMap,
    ) {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $user = $this->token->getToken()?->getUser();
        if (!$user instanceof Customer) {
            return;
        }
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $patterns = $this->accessMap->getPatterns($request);

        if (!isset($patterns[0][0]) || $patterns[0][0] !== 'ROLE_CUSTOMER') {
            return;
        }

        $usesForCustomer = $operation?->getExtraProperties()['usesForCustomer'] ?? null;
        if (null !== $usesForCustomer) {
            foreach ($usesForCustomer as $joinTable) {
                $use = 'use'.ucwords(strtolower($joinTable)).'Query';
                $query = $query->$use();
            }
            $query->filterByCustomer($user);
            $endUse = 'endUse';
            foreach ($usesForCustomer as $joinTable) {
                $query = $query->$endUse();
            }

            return;
        }
        if (method_exists($query, 'filterByCustomer')) {
            $query->filterByCustomer($user);
        }
    }
}
