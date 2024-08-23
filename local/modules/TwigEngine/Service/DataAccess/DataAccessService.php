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

namespace TwigEngine\Service\DataAccess;

use ApiPlatform\Metadata\Exception\ResourceClassNotFoundException;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Thelia\Service\Model\LangService;
use TwigEngine\Service\ApiPlatformMetadataService;

readonly class DataAccessService
{
    public function __construct(
        private RouterInterface $router,
        private ApiPlatformMetadataService $apiPlatformMetadataService,
        private LoopDataAccessService $loopDataAccessService,
        private NormalizerInterface $normalizer,
        private LangService $localeService
    ) {
    }

    /**
     * @throws ExceptionInterface
     * @throws ResourceClassNotFoundException
     */
    public function resources(string $path, array $parameters = []): object|array
    {
        /** @var Router $router */
        $router = $this->router;
        $route = $router->match($path);

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
            throw new AccessDeniedHttpException('Access Denied on '.$path);
        }

        $result = $this->apiPlatformMetadataService->getProvider($operation)->provide(
            $operation,
            [],
            $context
        );
        $datasNormalized = $this->normalizer->normalize($result, null, $context);

        return $this->formatI18ns($datasNormalized, $this->localeService->getLocale());
    }

    /** @deprecated use new data access layer */
    public function loop(string $loopName, string $loopType, array $params = []): array
    {
        return $this->loopDataAccessService->theliaLoop($loopName, $loopType, $params);
    }

    /** @deprecated use new data access layer */
    public function loopCount(string $loopType, array $params = []): int
    {
        return $this->loopDataAccessService->theliaCount($loopType, $params);
    }

    private function formatI18ns(array $datas, string $locale = null): array
    {
        foreach ($datas as $key => $data) {
            if ($key === 'i18ns' && isset($datas['i18ns'][$locale])) {
                $datas['i18ns'] = $datas['i18ns'][$locale];
                continue;
            }
            if (\is_array($data)) {
                $datas[$key] = $this->formatI18ns($data, $locale);
                continue;
            }
            $datas[$key] = $data;
        }

        return $datas;
    }
}
