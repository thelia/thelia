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

namespace Thelia\Core\Template\Twig\Service;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Exception\ResourceClassNotFoundException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\AccessMapInterface;
use Thelia\Api\Bridge\Propel\State\PropelCollectionProvider;
use Thelia\Api\Bridge\Propel\State\PropelItemProvider;
use Thelia\Core\Security\SecurityContext;

class ApiPlatformMetadataService
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        private readonly PropelCollectionProvider $propelCollectionProvider,
        private readonly PropelItemProvider $propelItemProvider,
        #[Autowire(service: 'security.access_map')]
        private readonly AccessMapInterface $accessMap,
        private readonly SecurityContext $securityContext,
        #[Autowire(service: 'api_platform.security.resource_access_checker')]
        private readonly ResourceAccessCheckerInterface $resourceAccessChecker,
    ) {
    }

    /**
     * @throws ResourceClassNotFoundException
     */
    public function getOperation(
        string $resourceClass,
        string $routeName
    ): ?Operation {
        $metadata = $this->resourceMetadataCollectionFactory->create($resourceClass);
        foreach ($metadata as $resourceMetadata) {
            /** @var Operation $operation */
            foreach ($resourceMetadata->getOperations() as $operation) {
                if ($operation->getName() === $routeName) {
                    return $operation;
                }
            }
        }

        return null;
    }

    public function getProvider(Operation $operation): ProviderInterface
    {
        if ($operation instanceof CollectionOperationInterface) {
            return $this->propelCollectionProvider;
        }

        return $this->propelItemProvider;
    }

    public function canUserAccessResource(
        string $resourceClass,
        string $path,
        string $method,
        Operation $operation,
        array $context
    ): bool {
        $isGranted = $operation->getSecurity();

        if (null !== $isGranted && null === $this->resourceAccessChecker) {
            throw new \LogicException('Cannot check security expression when SecurityBundle is not installed. Try running "composer require symfony/security-bundle".');
        }
        if ($isGranted !== null) {
            return $this->resourceAccessChecker->isGranted($resourceClass, $isGranted, $context['extra_variables'] ?? []);
        }
        $request = Request::create($path, $method);
        [$roles, $channel] = $this->accessMap->getPatterns($request);
        if (null === $roles) {
            return true;
        }
        $user = $this->securityContext->getAdminUser() ?? $this->securityContext->getCustomerUser();
        $userRoles = $user ? $user->getRoles() : [];

        if (!empty(array_intersect($userRoles, $roles))) {
            return true;
        }

        return false;
    }
}
