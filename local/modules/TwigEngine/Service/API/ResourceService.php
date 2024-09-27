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

namespace TwigEngine\Service\API;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Service\Model\LangService;

readonly class ResourceService
{
    public function __construct(
        private RequestBuilderService    $requestBuilder,
        private RouteMatcherService      $routeMatcher,
        private OperationProviderService $operationProvider,
        private ContextBuilderService    $contextBuilder,
        private AccessCheckerService     $accessChecker,
        private DataProviderService      $dataProvider,
        private NormalizerService        $normalizer,
        private RouterInterface          $router,
        private MetadataService          $metadataService,
        private RequestStack             $requestStack,
        private LangService              $localeService,
    ) {
    }

    public function resources(string $path, array $parameters = []): object|array
    {
        $apiRequest = $this->requestBuilder->createApiRequest($this->requestStack, $path);
        $route = $this->routeMatcher->matchRoute($this->router, $apiRequest);

        $operation = $this->operationProvider->getOperation($this->metadataService, $route);
        $resourceClass = $route['_api_resource_class'];
        $uriVariables = $this->operationProvider->getUriVariables($route, $operation, $resourceClass);

        $context = $this->contextBuilder->buildContext($path, $operation, $resourceClass, $uriVariables, $parameters);

        $this->accessChecker->checkUserAccess($resourceClass, $path, $operation, $context);

        $result = $this->dataProvider->fetchData($operation, $uriVariables, $context);
        $normalizedData = $this->normalizer->normalizeData($result, $context);

        return $this->formatI18ns($normalizedData, $this->localeService->getLocale());
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
