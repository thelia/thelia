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

namespace Thelia\Api\EventListener;

use ApiPlatform\Metadata\Exception\OperationNotFoundException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Api\Security\AdminApiResourcePermissions;
use Thelia\Core\HttpFoundation\RequestPath;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Admin;
use Thelia\Model\AdminLog;

/**
 * Applies the per-resource admin permissions to the whole /api/admin surface.
 *
 * The firewall only asks for ROLE_ADMIN there, which every administrator holds,
 * so a token alone says nothing about what its profile is allowed to reach. The
 * profile is what decides, exactly as it does on the back-office screens. The
 * check runs at priority 6, between the firewall that authenticates at 8 and the
 * API Platform read at 4, so a refused request never touches the database.
 *
 * A superadministrator keeps the short circuit it already has in the back-office.
 *
 * Which requests are admin requests is decided twice over, and either answer is enough:
 * from the path, spelled the way the router reads it, and from the operation the router
 * actually matched. The path alone was not enough — the router percent-decodes before
 * matching, so /api/%61dmin/... reached an admin operation without starting with
 * "/api/admin" — and the operation alone would leave out an admin controller that is not
 * an API Platform operation.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
final readonly class AdminApiPermissionListener
{
    private const string ADMIN_API_PREFIX = '/api/admin';

    /**
     * The uriTemplate of every admin operation starts with this; the /api in front of
     * it is the route prefix, which the template does not carry.
     */
    private const string ADMIN_OPERATION_PREFIX = '/admin';

    /**
     * Endpoints the kernel declares PUBLIC_ACCESS. They carry no API resource and
     * must stay reachable, in particular so a restricted admin can still log in.
     */
    private const array PUBLIC_PATHS = [
        '/api/admin/login',
        '/api/admin/token/refresh',
    ];

    private const array METHOD_ACCESSES = [
        'GET' => AccessManager::VIEW,
        'HEAD' => AccessManager::VIEW,
        'POST' => AccessManager::CREATE,
        'PUT' => AccessManager::UPDATE,
        'PATCH' => AccessManager::UPDATE,
        'DELETE' => AccessManager::DELETE,
    ];

    public function __construct(
        private Security $security,
        private SecurityContext $securityContext,
        private AdminApiResourcePermissions $permissions,
        private ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = RequestPath::decoded($request);

        if (\in_array($path, self::PUBLIC_PATHS, true)) {
            return;
        }

        $operation = $this->matchedOperation($request);

        if (!str_starts_with($path, self::ADMIN_API_PREFIX) && !$this->isAdminOperation($operation)) {
            return;
        }

        $user = $this->security->getUser();

        // Anyone else is already turned away by the ROLE_ADMIN rule on ^/api/admin.
        if (!$user instanceof Admin) {
            return;
        }

        if (AdminResources::SUPERADMINISTRATOR === $user->getPermissions()) {
            return;
        }

        $access = ($operation instanceof Operation ? ($operation->getExtraProperties()['admin_access'] ?? null) : null)
            ?? self::METHOD_ACCESSES[$request->getMethod()] ?? null;
        $resourceClass = $request->attributes->get('_api_resource_class');
        $resource = \is_string($resourceClass) ? $this->permissions->resolve($resourceClass) : null;

        // An endpoint nothing declares a permission for is refused rather than
        // opened: a module publishing its own admin resources registers them in
        // the "thelia.api.admin_resources" parameter.
        if (null === $access || null === $resource) {
            $this->deny($user, $request, $path, $request->getMethod());
        }

        if (!$this->securityContext->isUserGranted(['ADMIN'], [$resource], [], [$access], $user)) {
            $this->deny($user, $request, $resource, $access);
        }
    }

    /**
     * The operation the router matched, whether or not API Platform has resolved it yet.
     *
     * API Platform sets "_api_operation" lazily, from its own listeners, which run after
     * this one; what the router itself leaves on the request is the resource class and
     * the operation name, which is enough to look the operation up.
     */
    private function matchedOperation(Request $request): ?Operation
    {
        $operation = $request->attributes->get('_api_operation');

        if ($operation instanceof Operation) {
            return $operation;
        }

        $resourceClass = $request->attributes->get('_api_resource_class');
        $operationName = $request->attributes->get('_api_operation_name');

        if (!\is_string($resourceClass) || !\is_string($operationName)) {
            return null;
        }

        try {
            return $this->resourceMetadataCollectionFactory->create($resourceClass)->getOperation($operationName);
        } catch (OperationNotFoundException) {
            return null;
        }
    }

    private function isAdminOperation(?Operation $operation): bool
    {
        return $operation instanceof Operation
            && str_starts_with((string) $operation->getUriTemplate(), self::ADMIN_OPERATION_PREFIX);
    }

    private function deny(Admin $user, Request $request, string $resource, string $access): never
    {
        AdminLog::append(
            $resource,
            $access,
            \sprintf('Admin API: user is not granted for resource [%s] with access [%s]', $resource, $access),
            $request,
            $user,
        );

        throw new AccessDeniedHttpException('You are not allowed to perform this action.');
    }
}
