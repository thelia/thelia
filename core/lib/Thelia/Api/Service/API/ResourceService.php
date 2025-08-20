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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Log\Tlog;
use Thelia\Service\Model\LangService;

readonly class ResourceService
{
    public function __construct(
        private RequestBuilderService $requestBuilder,
        private RouteMatcherService $routeMatcher,
        private OperationProviderService $operationProvider,
        private ContextBuilderService $contextBuilder,
        private AccessCheckerService $accessChecker,
        private DataProviderService $dataProvider,
        private NormalizerService $normalizer,
        private RouterInterface $router,
        private MetadataService $metadataService,
        private RequestStack $requestStack,
        private LangService $localeService,
    ) {
    }

    public function resources(string $path, array $parameters = [], ?string $format = null): object|array|null
    {
        $apiRequest = $this->requestBuilder->createApiRequest($this->requestStack, $path);
        $route = $this->routeMatcher->matchRoute($this->router, $apiRequest);
        $currentLocale = $this->localeService->getLocale();
        $operation = $this->operationProvider->getOperation($this->metadataService, $route);
        $resourceClass = $route['_api_resource_class'];
        $uriVariables = $this->operationProvider->getUriVariables($route, $operation, $resourceClass);
        $parameters = $this->manageLocale($parameters);
        $context = $this->contextBuilder->buildContext($path, $operation, $resourceClass, $uriVariables, $parameters);

        $result = $this->dataProvider->fetchData($operation, $uriVariables, $context);
        if ($result === null) {
            return null;
        }
        try {
            $context['extra_variables']['object'] = $result;
            $this->accessChecker->checkUserAccess($resourceClass, $path, $operation, $context);
        } catch (\Exception $e) {
            Tlog::getInstance()->error(
                sprintf(
                    'Error while checking access for resource "%s" at path "%s": %s',
                    $resourceClass,
                    $path,
                    $e->getMessage()
                )
            );
            return null;
        }
        $normalizedData = $this->normalizer->normalizeData($result, $context, $format);
        if ($this->isTranslatableResult($result)) {
            // can't use Serializer in this use case, so need to manually add publicUrl
            if ($format === null) {
                $normalizedData = $this->addPublicUrl($result, $normalizedData, $currentLocale);
            }
            if ($format === 'jsonld') {
                $normalizedData = $this->addPublicUrlWithJsonLd($result, $normalizedData, $currentLocale);
            }
        }

        return $this->formatI18ns($normalizedData, $currentLocale);
    }

    private function manageLocale(array $parameters): array
    {
        if (isset($parameters['locale'])) {
            return $parameters;
        }
        $locale = $this->localeService->getLocale();
        $parameters['locale'] = $locale;

        return $parameters;
    }

    private function formatI18ns(array $datas, ?string $locale = null): array
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

    private function isTranslatableResult(mixed $result): bool
    {
        if (!\is_array($result)) {
            return is_a($result, TranslatableResourceInterface::class);
        }

        return isset($result[0]) && is_a($result[0], TranslatableResourceInterface::class);
    }

    private function addPublicUrlWithJsonLd(mixed $result, array $normalizedData, string $currentLocale): array
    {
        $isMultidimensional = (isset($normalizedData['hydra:totalItems']));

        if (!$isMultidimensional) {
            return $this->addUrlToEntry($result, $normalizedData, $currentLocale);
        }

        foreach ($normalizedData['hydra:member'] as $key => $entry) {
            $normalizedData['hydra:member'][$key] = $this->addUrlToEntry($result[$key], $entry, $currentLocale);
        }

        return $normalizedData;
    }

    private function addPublicUrl(mixed $result, array $normalizedData, string $currentLocale): array
    {
        $finalNormalizedData = [];
        $isMultidimensional = isset($normalizedData[0]);

        if (!$isMultidimensional) {
            return $this->addUrlToEntry($result, $normalizedData, $currentLocale);
        }

        foreach ($normalizedData as $key => $entry) {
            $finalNormalizedData[] = $this->addUrlToEntry($result[$key], $entry, $currentLocale);
        }

        return $finalNormalizedData;
    }

    private function addUrlToEntry(mixed $resource, array $entry, string $currentLocale): array
    {
        if (method_exists($resource, 'getUrl')) {
            $entry['publicUrl'] = $resource->getUrl($currentLocale);
        }

        return $entry;
    }
}
