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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Element\BaseLoop;

readonly class DataAccessService
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private ContainerInterface $container,
        private EventDispatcherInterface $eventDispatcher,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        private RequestStack $requestStack,
        private RouterInterface $router,
        private ApiPlatformMetadataService $apiPlatformMetadataService,
    ) {
    }

    public function resources(string $path, array $parameters = []): object|array
    {
        $route = $this->router->match($path);

        $resourceClass = $route['_api_resource_class'];
        $routeName = $route['_route'];

        $operation = $this->apiPlatformMetadataService->getOperation(
            $resourceClass,
            $routeName
        );
        if ($operation === null) {
            throw new \RuntimeException('Operation not found');
        }

        $context = [
            'operation' => $operation,
            'uri_variables' => [],
            'resource_class' => $resourceClass,
            'filters' => $parameters,
            'groups' => $operation->getNormalizationContext()['groups'] ?? null,
        ];

        if (
            !$this->apiPlatformMetadataService->canUserAccessResource(
                $resourceClass,
                $path,
                Request::METHOD_GET,
                $operation,
                $context
            )
        ) {
            throw new AccessDeniedHttpException('Access Denied');
        }

        return $this->apiPlatformMetadataService->getProvider($operation)->provide(
            $operation,
            [],
            $context
        );
    }

    /** @deprecated use new data access layer */
    public function loop(string $loopName, array $params = []): mixed
    {
        $loopNamespace = 'Thelia\\Core\\Template\\Loop\\';
        $className = ucfirst(strtolower($loopName));
        if (!class_exists($loopNamespace.$className)) {
            throw new \RuntimeException('Loop '.$className.' not found');
        }
        $fullClassName = $loopNamespace.$className;

        /** @var BaseLoop $instance */
        $instance = new $fullClassName();

        $instance->init(
            $this->container,
            $this->requestStack,
            $this->eventDispatcher,
            $this->securityContext,
            $this->translator,
            $this->parameterBag->get('Thelia.parser.loops'),
            $this->parameterBag->get('kernel.environment')
        );
        $instance->initializeArgs($params);
        $loopResults = $instance->exec($pagination);

        $datas = [];
        $count = 0;
        for ($loopResults->rewind(); $loopResults->valid(); $loopResults->next()) {
            $loopResult = $loopResults->current();

            foreach ($loopResult->getVars() as $key) {
                $datas[$count][$key] = $loopResult->get($key);
            }
            ++$count;
        }

        return $datas;
    }
}
