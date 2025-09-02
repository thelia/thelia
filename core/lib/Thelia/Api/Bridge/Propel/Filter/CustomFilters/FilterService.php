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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\CategoryFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaChoiceFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Type\CheckboxType;
use Thelia\Api\Resource\Filter;
use Thelia\Api\Resource\FilterValue;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ChoiceFilter;
use Thelia\Model\ChoiceFilterQuery;
use Thelia\Service\Model\LangService;

readonly class FilterService
{
    public function __construct(
        #[TaggedIterator('api.thelia.filter')]
        private readonly iterable $filters,
        #[TaggedIterator('api.thelia.filter.type')]
        private readonly iterable $filterTypes,
        private readonly LangService $langService,
        private readonly RequestStack $requestStack,
        private readonly Translator $translator,
    ) {
    }

    private function getAvailableFiltersWithTFilter(string $resourceType, array $tfilters): array
    {
        $filters = $this->getAvailableFilters($resourceType);
        $filterResult = [];

        foreach ($filters as $filter) {
            foreach ($tfilters as $tfilter => $tfilterValue) {
                if (\in_array($tfilter, $filter->getFilterName(), true)) {
                    $filterResult[] = [
                        'filter' => $filter,
                        'tfilter' => $tfilter,
                        'value' => $tfilterValue,
                        'resourceType' => $resourceType,
                    ];
                }
            }
        }

        return $filterResult;
    }

    public function getAvailableFilters(string $resourceType): array
    {
        $filters = [];

        foreach ($this->filters as $filter) {
            if (\in_array($resourceType, $filter->getResourceType(), true)) {
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    public function filterTFilterWithRequest($request, ?ModelCriteria $query = null): iterable
    {
        $tfilters = $request->get('tfilters', []);
        $categoryDepth = (int) $request->get(CategoryFilter::CATEGORY_DEPTH_NAME, null);
        $pathInfo = $request->getPathInfo();
        $segments = explode('/', (string) $pathInfo);
        $resource = end($segments);

        return $this->filterWithTFilter(tfilters: $tfilters, resource: $resource, query: $query, categoryDepth: $categoryDepth);
    }

    public function filterTFilterWithContext(?array $context = null, ?ModelCriteria $query = null): iterable
    {
        $tfilters = $context['filters']['tfilters'] ?? [];
        $categoryDepth = (int) ($context['filters'][CategoryFilter::CATEGORY_DEPTH_NAME] ?? null);
        $pathInfo = $context['path_info'];
        $segments = explode('/', (string) $pathInfo);
        $resource = end($segments);

        return $this->filterWithTFilter(tfilters: $tfilters, resource: $resource, query: $query, categoryDepth: $categoryDepth);
    }

    public function filterWithTFilter(array $tfilters, string $resource, ?ModelCriteria $query = null, ?int $categoryDepth = null): iterable
    {
        $filters = $this->getAvailableFiltersWithTFilter($resource, $tfilters);

        if (!$query instanceof ModelCriteria) {
            $queryClass = 'Thelia\\Model\\'.ucfirst($resource).'Query';

            if (!class_exists($queryClass)) {
                $queryClass = 'Thelia\\Model\\'.ucfirst(mb_substr($resource, 0, -1)).'Query';
            }

            if (!class_exists($queryClass)) {
                throw new \RuntimeException('Not found class: '.$queryClass);
            }

            $query = $queryClass::create();
        }

        foreach ($filters as $filter) {
            $filterClass = $filter['filter'];
            $values = $filter['value'];

            if (!$filterClass instanceof TheliaFilterInterface) {
                throw new \RuntimeException(\sprintf('The "%s" filter must implements TheliaFilterInterface.', $filterClass::class));
            }

            if (\is_string($values)) {
                $values = explode(',', $values);
            }
            if (!\is_array($values) || empty($values)) {
                continue;
            }
            $isMinOrMaxFilter = $this->isMinOrMaxFilter($values);

            if ($filterClass instanceof CategoryFilter) {
                $filterClass->filter(query: $query, value: $values, categoryDepth: $categoryDepth);
            } else {
                $filterClass->filter(query: $query, value: $values, isMinOrMaxFilter: $isMinOrMaxFilter);
            }
        }

        return $query->groupById();
    }

    public function getFilters(array $context, string $resource): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            throw new \InvalidArgumentException('The request is required.');
        }

        $isApiRoute = $request->get('isApiRoute', false);

        if ($isApiRoute) {
            $tfilters = $request->get('tfilters', []);
            $query = $this->filterTFilterWithRequest(request: $request);
        } else {
            $tfilters = $context['filters']['tfilters'] ?? [];
            $query = $this->filterTFilterWithContext(context: $context);
        }

        $filterObjects = [];
        $locale = $context['filters']['locale'] ?? $request->get('locale');
        $locale ??= $this->langService->getLocale();
        $filters = $this->getAvailableFilters($resource);

        foreach ($filters as $filter) {
            $values = $this->getValues(
                query: $query,
                filter: $filter,
                tfilters: $tfilters,
                locale: $locale
            );
            if ($values === []) {
                continue;
            }
            $hasMainResource = $this->hasMainResource($values);
            if ($hasMainResource) {
                $values = array_intersect_key(
                    $values, array_unique(
                        array_map(
                            static fn (FilterValue $filterValue): string => $filterValue->getId().'-'.$filterValue->getMainId(),
                            $values,
                        )
                    )
                );

                $splitValues = [];

                /** @var FilterValue $value */
                foreach ($values as $value) {
                    $splitValues[$value->getMainId()][] = $value;
                }

                foreach ($splitValues as $value) {
                    $filterDto = $this->createFilterDto(
                        tfilters: $tfilters,
                        filter: $filter,
                        values: $value,
                        locale: $locale
                    );
                    if (!$filterDto || !$filterDto->isVisible()) {
                        continue;
                    }
                    $filterObjects[] = $filterDto;
                }
            }
            if (!$hasMainResource) {
                $values = array_values(
                    array_reduce(
                        $values,
                        static function ($carry, $item) {
                            $carry[$item->getId()] = $item;

                            return $carry;
                        },
                        []
                    )
                );

                $filterDto = $this->createFilterDto(
                    tfilters: $tfilters,
                    filter: $filter,
                    values: $values,
                    locale: $locale,
                );

                if (!$filterDto || !$filterDto->isVisible()) {
                    continue;
                }
                $filterObjects[] = $filterDto;
            }
        }

        return $this->managePosition($filterObjects);
    }

    private function managePosition(array $filterObjects): array
    {
        foreach ($filterObjects as $filterObject) {
            if (null === $filterObject->getPosition()) {
                $allPosition = array_map(static fn ($filterObject): ?int => $filterObject->getPosition(), $filterObjects);
                $max = max($allPosition);
                $filterObject->setPosition($max + 1);
            }
        }

        usort($filterObjects, static fn ($a, $b): int => $a->getPosition() <=> $b->getPosition());

        return $filterObjects;
    }

    private function getValues($query, $filter, $tfilters, $locale): array
    {
        $objects = $query->find();
        $values = [];

        foreach ($objects as $item) {
            if ($filter instanceof CategoryFilter) {
                $categoryId = $this->retrieveFilterValue(
                    theliaFilterNames: CategoryFilter::getFilterName(),
                    tfilters: $tfilters,
                );
                $depth = $tfilters[CategoryFilter::CATEGORY_DEPTH_NAME] ?? 1;
                $values = $filter->getValue(
                    activeRecord: $item,
                    locale: $locale,
                    valueSearched: $categoryId,
                    depth: $depth,
                );
                break;
            }

            $possibleValues = $filter->getValue(
                activeRecord: $item,
                locale: $locale,
            );

            if (!$possibleValues) {
                continue;
            }

            foreach ($possibleValues as $value) {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function retrieveFilterValue(array $theliaFilterNames, array $tfilters): string|array|int|null
    {
        $ids = null;

        foreach ($theliaFilterNames as $filterName) {
            if (!isset($tfilters[$filterName])) {
                continue;
            }

            $ids = $tfilters[$filterName];
        }
        while (\is_array($ids) && \count($ids) === 1) {
            $ids = reset($ids);
        }

        return $ids;
    }

    private function createFilterDto(array $tfilters, TheliaFilterInterface $filter, array $values, string $locale): ?Filter
    {
        if (!$this->hasFilter(theliaFilterNames: CategoryFilter::getFilterName(), tfilters: $tfilters)) {
            return null;
        }

        $categoryId = $this->retrieveFilterValue(theliaFilterNames: CategoryFilter::getFilterName(), tfilters: $tfilters);
        $category = CategoryQuery::create()->findPk(key: $categoryId);
        $choiceFiltersCategory = ChoiceFilterQuery::findChoiceFilterByCategory(category: $category, templateId: $templateIdFind)->getData();
        $choiceFiltersTemplate = [];

        if ($templateIdFind) {
            $choiceFiltersTemplate = ChoiceFilterQuery::create()->filterByTemplateId($templateIdFind)->find()->getData();
        }

        $choiceFilters = $choiceFiltersCategory;

        if (empty($choiceFilters)) {
            $choiceFilters = $choiceFiltersTemplate;
        }

        if (empty($choiceFilters) && $templateIdFind === null) {
            return null;
        }

        /** @var ChoiceFilter $choiceFilter */
        foreach ($choiceFilters as $choiceFilter) {
            $otherType = $choiceFilter->getChoiceFilterOther()?->getType();

            if (\in_array($otherType, $filter->getFilterName(), true)) {
                return $this->hydrateFilterDto(
                    filter: $filter,
                    values: $values,
                    locale: $locale,
                    choiceFilter: $choiceFilter,
                );
            }

            /**
             * @var FilterValue $value
             */
            foreach ($values as $value) {
                if ($filter instanceof TheliaChoiceFilterInterface) {
                    $mainType = $filter->getChoiceFilterType();

                    if ($choiceFilter->getAttribute() instanceof $mainType && $choiceFilter->getAttribute()->getId() === $value->getMainId()) {
                        return $this->hydrateFilterDto(
                            filter: $filter,
                            values: $values,
                            locale: $locale,
                            choiceFilter: $choiceFilter,
                        );
                    }

                    if ($choiceFilter->getFeature() instanceof $mainType && $choiceFilter->getFeature()->getId() === $value->getMainId()) {
                        return $this->hydrateFilterDto(
                            filter: $filter,
                            values: $values,
                            locale: $locale,
                            choiceFilter: $choiceFilter,
                        );
                    }
                }
            }
        }

        return $this->hydrateFilterDto(
            filter: $filter,
            values: $values,
            locale: $locale
        );
    }

    private function hydrateFilterDto(
        TheliaFilterInterface $filter,
        array $values,
        ?string $locale,
        ?ChoiceFilter $choiceFilter = null,
    ): Filter {
        $mainTitle = null;
        $mainId = null;
        /** @var FilterValue $value */
        foreach ($values as $value) {
            $mainTitle = $value->getMainTitle();
            $mainId = $value->getMainId();
            break;
        }
        if (!$mainTitle) {
            $mainTitle = $filter::getFilterName()[0];
        }
        if ($filter instanceof CategoryFilter) {
            $mainTitle = $this->translator->trans(id: 'Category', locale: $locale);
        }
        $position = null;
        $isVisible = true;
        $fieldType = CheckboxType::getName();
        if ($choiceFilter) {
            $position = $choiceFilter->getPosition();
            $fieldType = $choiceFilter->getType();
            $isVisible = (bool) $choiceFilter->isVisible();
        }
        $filterDto = new Filter();
        $filterDto->setVisible($isVisible);
        $filterDto->setPosition($position);
        $filterDto->setFieldType($fieldType);
        $filterDto->setType($filter::getFilterName()[0]);
        $filterDto->setId($mainId);
        $filterDto->setTitle($mainTitle);
        $filterDto->setValues($values);

        return $filterDto;
    }

    private function hasFilter(array $theliaFilterNames, array $tfilters): bool
    {
        return !\in_array($this->retrieveFilterValue($theliaFilterNames, $tfilters), ['', '0', null], true)
            && [] !== $this->retrieveFilterValue($theliaFilterNames, $tfilters);
    }

    public function getCategoriesRecursively($categoryId, int $maxDepth, array $categoriesFound = [], int $depth = 1): array
    {
        $categories = CategoryQuery::create()->filterByParent($categoryId)->find();

        if ($depth > $maxDepth) {
            return $categoriesFound;
        }

        foreach ($categories as $category) {
            if (!$category->getVisible()) {
                continue;
            }

            $categoriesFound[$depth][] = $category;
            $categoriesFound = $this->getCategoriesRecursively(
                categoryId: $category->getId(),
                maxDepth: $maxDepth,
                categoriesFound: $categoriesFound,
                depth: $depth + 1,
            );
        }

        return $categoriesFound;
    }

    public function getFilterTypes(): array
    {
        $filters = [];
        foreach ($this->filterTypes as $filterType) {
            $filters[] = $filterType->getName();
        }

        return $filters;
    }

    private function hasMainResource(array $values): bool
    {
        /** @var FilterValue $value */
        foreach ($values as $value) {
            return $value->getMainId() && $value->getMainTitle();
        }
    }

    private function isMinOrMaxFilter(array $values): bool
    {
        foreach ($values as $value) {
            if (!\is_array($value)) {
                return false;
            }
            if (\array_key_exists('min', $value) || \array_key_exists('max', $value)) {
                return true;
            }
        }

        return false;
    }
}
