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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\BrandFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\CategoryFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaAggregatedFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaChoiceFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Type\CheckboxType;
use Thelia\Api\Resource\Filter;
use Thelia\Api\Resource\FilterValue;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ChoiceFilter;
use Thelia\Model\ChoiceFilterQuery;

readonly class FilterService
{
    public function __construct(
        #[AutowireIterator('api.thelia.filter')]
        private readonly iterable $filters,
        #[AutowireIterator('api.thelia.filter.type')]
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
        $tfilters = $request->query->all('tfilters');
        $categoryDepth = (int) $request->query->get(CategoryFilter::CATEGORY_DEPTH_NAME);
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

    /**
     * The facets of the browsed set. A filter that is part of the selection reads its facet
     * from the set narrowed by every other filter but itself, so a checked value keeps its
     * siblings on offer (checking one brand must not hide the other brands); a filter that is
     * not selected reads it from the fully narrowed set. The category filter is the browsing
     * scope, never relaxed. A collection restricted to a set of ids (`id[]=…`, the products a
     * search engine ranked) gets its facets from that set, category or not.
     */
    public function getFilters(array $context, string $resource): array
    {
        $request = $this->requestStack->getMainRequest();

        if (!$request instanceof Request) {
            throw new \InvalidArgumentException('The request is required.');
        }

        $isApiRoute = $request->request->get('isApiRoute', false);

        if ($isApiRoute) {
            $tfilters = $request->query->all('tfilters');
            $visible = $request->query->get('visible');
            $scopeIds = $request->query->all('id');
            $categoryDepth = (int) $request->query->get(CategoryFilter::CATEGORY_DEPTH_NAME);
        } else {
            $tfilters = $context['filters']['tfilters'] ?? [];
            $visible = $context['filters']['visible'] ?? null;
            $scopeIds = $context['filters']['id'] ?? [];
            $categoryDepth = (int) ($context['filters'][CategoryFilter::CATEGORY_DEPTH_NAME] ?? null);
        }

        $scopeIds = $this->scopeIds($scopeIds);
        $browsesCategory = $this->hasFilter(theliaFilterNames: CategoryFilter::getFilterName(), tfilters: $tfilters);

        // Without a browsed category nor an explicit set of ids the facets would describe the
        // whole catalogue, which no listing shows.
        if (!$browsesCategory && $scopeIds === null) {
            return [];
        }

        $locale = $context['filters']['locale'] ?? $request->query->get('locale');
        $locale ??= $this->langService->getLocale();

        $resolveIds = function (array $selection) use ($resource, $visible, $categoryDepth, $scopeIds): array {
            $query = $this->filterWithTFilter(tfilters: $selection, resource: $resource, categoryDepth: $categoryDepth);
            $this->restrictToVisibility($query, $visible);
            $this->restrictToScope($query, $scopeIds);

            return $this->resolveResourceIds($query);
        };

        $narrowedIds = $resolveIds($tfilters);
        $narrowedQuery = null;
        $choiceFilters = $this->choiceFiltersOfBrowsedCategory($tfilters);
        $filterObjects = [];

        foreach ($this->getAvailableFilters($resource) as $filter) {
            if ($filter instanceof TheliaAggregatedFilterInterface) {
                $values = $this->aggregatedFacet(
                    filter: $filter,
                    tfilters: $tfilters,
                    narrowedIds: $narrowedIds,
                    resolveIds: $resolveIds,
                    locale: $locale,
                );
            } else {
                if ($narrowedIds === []) {
                    continue;
                }
                $narrowedQuery ??= $this->restrictedQuery($tfilters, $resource, $visible, $categoryDepth, $scopeIds);
                $values = $this->getValues(query: $narrowedQuery, filter: $filter, tfilters: $tfilters, locale: $locale);
            }

            if ($values === []) {
                continue;
            }

            foreach ($this->groupByMainResource($values) as $group) {
                $filterDto = $this->createFilterDto(filter: $filter, values: $group, locale: $locale, choiceFilters: $choiceFilters);

                if (!$filterDto || !$filterDto->isVisible()) {
                    continue;
                }

                $filterObjects[] = $filterDto;
            }
        }

        return $this->managePosition($filterObjects);
    }

    /**
     * @param array<int>|null $scopeIds
     */
    private function restrictedQuery(array $tfilters, string $resource, mixed $visible, int $categoryDepth, ?array $scopeIds): ModelCriteria
    {
        $query = $this->filterWithTFilter(tfilters: $tfilters, resource: $resource, categoryDepth: $categoryDepth);
        $this->restrictToVisibility($query, $visible);
        $this->restrictToScope($query, $scopeIds);

        return $query;
    }

    /**
     * The `id` parameter of the collection, as a search page sends it: the facets then describe
     * the products the search engine ranked, not the catalogue. Null when the request sends none.
     *
     * @return array<int>|null
     */
    private function scopeIds(mixed $ids): ?array
    {
        if ($ids === null || $ids === '' || $ids === []) {
            return null;
        }

        if (\is_string($ids)) {
            $ids = explode(',', $ids);
        }

        $ids = array_values(array_unique(array_map('intval', array_filter((array) $ids, 'is_scalar'))));

        return $ids === [] ? null : $ids;
    }

    /**
     * @param array<int>|null $scopeIds
     */
    private function restrictToScope(ModelCriteria $query, ?array $scopeIds): void
    {
        if ($scopeIds === null) {
            return;
        }

        $query->filterById($scopeIds, Criteria::IN);
    }

    /**
     * The facet values of one aggregated filter. When the filter is selected, the values of
     * each selected group (a feature, an attribute, or the filter as a whole for a brand) are
     * read from the set narrowed by everything but that group; the other groups keep the fully
     * narrowed set.
     *
     * @param array<int>                  $narrowedIds
     * @param callable(array): array<int> $resolveIds
     *
     * @return array<FilterValue>
     */
    private function aggregatedFacet(TheliaAggregatedFilterInterface $filter, array $tfilters, array $narrowedIds, callable $resolveIds, string $locale): array
    {
        if ($filter instanceof CategoryFilter) {
            return $narrowedIds === [] ? [] : $this->getAggregatedValues($filter, $narrowedIds, $tfilters, $locale);
        }

        $selectedKeys = array_values(array_filter(
            $filter::getFilterName(),
            static fn (string $name): bool => isset($tfilters[$name]) && $tfilters[$name] !== [] && $tfilters[$name] !== '',
        ));

        if ($selectedKeys === []) {
            return $narrowedIds === [] ? [] : $this->getAggregatedValues($filter, $narrowedIds, $tfilters, $locale);
        }

        if (!$filter instanceof TheliaChoiceFilterInterface) {
            $relaxed = $tfilters;
            foreach ($selectedKeys as $key) {
                unset($relaxed[$key]);
            }

            $relaxedIds = $resolveIds($relaxed);

            return $relaxedIds === [] ? [] : $this->getAggregatedValues($filter, $relaxedIds, $tfilters, $locale);
        }

        $values = $narrowedIds === [] ? [] : $this->getAggregatedValues($filter, $narrowedIds, $tfilters, $locale);

        foreach ($selectedKeys as $key) {
            foreach (array_keys((array) $tfilters[$key]) as $group) {
                $relaxed = $tfilters;
                unset($relaxed[$key][$group]);

                if ($relaxed[$key] === []) {
                    unset($relaxed[$key]);
                }

                $relaxedIds = $resolveIds($relaxed);
                $values = array_values(array_filter(
                    $values,
                    static fn (FilterValue $value): bool => (string) $value->getMainId() !== (string) $group,
                ));

                if ($relaxedIds === []) {
                    continue;
                }

                foreach ($this->getAggregatedValues($filter, $relaxedIds, $tfilters, $locale) as $value) {
                    if ((string) $value->getMainId() === (string) $group) {
                        $values[] = $value;
                    }
                }
            }
        }

        return $values;
    }

    /**
     * Splits the values by the entity they hang from (a feature, an attribute), deduplicated;
     * values without one (a brand, a category) form a single group.
     *
     * @param array<FilterValue> $values
     *
     * @return array<array<FilterValue>>
     */
    private function groupByMainResource(array $values): array
    {
        if (!$this->hasMainResource($values)) {
            $unique = [];

            foreach ($values as $value) {
                $unique[$value->getId()] ??= $value;
            }

            return [array_values($unique)];
        }

        $groups = [];

        foreach ($values as $value) {
            $groups[$value->getMainId()][$value->getId()] ??= $value;
        }

        return array_map('array_values', array_values($groups));
    }

    /**
     * The choice_filter rows that rule the browsed category, read once for every filter of
     * the page rather than once per facet.
     *
     * @return array{rows: array<ChoiceFilter>, template_id: int|null}|null null when nothing rules them
     */
    private function choiceFiltersOfBrowsedCategory(array $tfilters): ?array
    {
        if (!$this->hasFilter(theliaFilterNames: CategoryFilter::getFilterName(), tfilters: $tfilters)) {
            // A listing outside any category (a search) has no choice_filter rows to obey: every
            // filter is offered, as a checkbox list.
            return ['rows' => [], 'template_id' => null];
        }

        $categoryId = $this->retrieveFilterValue(theliaFilterNames: CategoryFilter::getFilterName(), tfilters: $tfilters);
        $category = CategoryQuery::create()->findPk(key: $categoryId);

        if (!$category) {
            return null;
        }

        $templateId = null;
        $rows = ChoiceFilterQuery::findChoiceFilterByCategory(category: $category, templateId: $templateId)->getData();

        if ($rows === [] && $templateId) {
            $rows = ChoiceFilterQuery::create()->filterByTemplateId($templateId)->find()->getData();
        }

        if ($rows === [] && $templateId === null) {
            return null;
        }

        return ['rows' => $rows, 'template_id' => $templateId];
    }

    /**
     * The facets follow the listing: a `visible` parameter narrows the set they are read from
     * the way the collection's own filter narrows the products, so a value held only by hidden
     * products is not offered to filter a list that will never show them.
     */
    private function restrictToVisibility(ModelCriteria $query, mixed $visible): void
    {
        if ($visible === null || $visible === '' || !method_exists($query, 'filterByVisible')) {
            return;
        }

        $query->filterByVisible(filter_var($visible, \FILTER_VALIDATE_BOOL) ? 1 : 0);
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

    /**
     * The identifiers of the filtered set, read once and shared by every filter:
     * a facet list needs to know which records are in the set, not what they hold.
     *
     * @return array<int>
     */
    private function resolveResourceIds(ModelCriteria $query): array
    {
        $ids = (clone $query)->select('Id')->find()->getData();

        return array_map('intval', $ids);
    }

    /**
     * @param array<int> $resourceIds
     *
     * @return array<FilterValue>
     */
    private function getAggregatedValues(TheliaAggregatedFilterInterface $filter, array $resourceIds, array $tfilters, string $locale): array
    {
        if ($filter instanceof CategoryFilter) {
            return $filter->getAggregatedValues(
                resourceIds: $resourceIds,
                locale: $locale,
                valueSearched: $this->retrieveFilterValue(
                    theliaFilterNames: CategoryFilter::getFilterName(),
                    tfilters: $tfilters,
                ),
                depth: (int) ($tfilters[CategoryFilter::CATEGORY_DEPTH_NAME] ?? 1),
            );
        }

        return $filter->getAggregatedValues(resourceIds: $resourceIds, locale: $locale);
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

    /**
     * @param array<FilterValue>                                           $values
     * @param array{rows: array<ChoiceFilter>, template_id: int|null}|null $choiceFilters
     */
    private function createFilterDto(TheliaFilterInterface $filter, array $values, string $locale, ?array $choiceFilters): ?Filter
    {
        if ($choiceFilters === null) {
            return null;
        }

        /** @var ChoiceFilter $choiceFilter */
        foreach ($choiceFilters['rows'] as $choiceFilter) {
            $otherType = $choiceFilter->getChoiceFilterOther()?->getType();

            if (\in_array($otherType, $filter->getFilterName(), true)) {
                return $this->hydrateFilterDto(filter: $filter, values: $values, locale: $locale, choiceFilter: $choiceFilter);
            }

            if (!$filter instanceof TheliaChoiceFilterInterface) {
                continue;
            }

            $mainType = $filter->getChoiceFilterType();

            /** @var FilterValue $value */
            foreach ($values as $value) {
                if ($choiceFilter->getAttribute() instanceof $mainType && $choiceFilter->getAttribute()->getId() === $value->getMainId()) {
                    return $this->hydrateFilterDto(filter: $filter, values: $values, locale: $locale, choiceFilter: $choiceFilter);
                }

                if ($choiceFilter->getFeature() instanceof $mainType && $choiceFilter->getFeature()->getId() === $value->getMainId()) {
                    return $this->hydrateFilterDto(filter: $filter, values: $values, locale: $locale, choiceFilter: $choiceFilter);
                }
            }
        }

        return $this->hydrateFilterDto(filter: $filter, values: $values, locale: $locale);
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
        if ($filter instanceof BrandFilter) {
            $mainTitle = $this->translator->trans(id: 'Brand', locale: $locale);
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

        return false;
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
