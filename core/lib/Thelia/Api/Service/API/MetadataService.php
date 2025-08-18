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

namespace Thelia\Api\Service\API;

use ApiPlatform\Metadata\Exception\ResourceClassNotFoundException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\AccessMapInterface;
use Thelia\Core\Security\SecurityContext;

readonly class MetadataService
{
    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        #[Autowire(service: 'security.access_map')]
        private AccessMapInterface $accessMap,
        private SecurityContext $securityContext,
        #[Autowire(service: 'api_platform.security.expression_language')]
        private readonly ?ExpressionLanguage $expressionLanguage = null,
        #[Autowire(service: 'security.authentication.trust_resolver')]
        private readonly ?AuthenticationTrustResolverInterface $authenticationTrustResolver = null,
        #[Autowire(service: 'security.authorization_checker')]
        private readonly ?AuthorizationCheckerInterface $authorizationChecker = null,
    ) {
    }

    /**
     * @throws ResourceClassNotFoundException
     */
    public function getOperation(
        string $resourceClass,
        string $routeName,
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

    public function canUserAccessResource(
        string $resourceClass,
        string $path,
        string $method,
        Operation $operation,
        array $context,
    ): bool {
        $expression = $operation->getSecurity();
        if (null !== $expression && null === $this->authenticationTrustResolver) {
            throw new \LogicException('The "symfony/security" library must be installed to use the "security" attribute.');
        }
        if (null === $this->expressionLanguage) {
            throw new \LogicException('The "symfony/expression-language" library must be installed to use the "security" attribute.');
        }
        $user = $this->isAdminRoute($path)
            ? $this->securityContext->getAdminUser()
            : $this->securityContext->getCustomerUser();

        if ($expression !== null) {
            $variables = array_merge($context['extra_variables'] ?? [], [
                'user' => $user,
                'auth_checker' => $this->authorizationChecker, // needed for the is_granted expression function
            ]);

            return (bool) $this->expressionLanguage->evaluate($expression, $variables);
        }
        $request = Request::create($path, $method);
        [$roles, $channel] = $this->accessMap->getPatterns($request);
        if (null === $roles) {
            return true;
        }
        $userRoles = $user ? $user->getRoles() : [];

        if (!empty(array_intersect($userRoles, $roles))) {
            return true;
        }

        return false;
    }

    private function isAdminRoute(string $path): bool
    {
        return str_starts_with($path, '/api/admin/');
    }
}
