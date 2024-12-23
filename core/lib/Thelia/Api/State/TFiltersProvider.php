<?php

namespace Thelia\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use InvalidArgumentException;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\CategoryFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaChoiceFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\PriceFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Resource\Filter;
use Thelia\Model\ChoiceFilterQuery;

class TFiltersProvider implements ProviderInterface
{

    public function __construct(protected FilterService $filterService)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $resource = $uriVariables['resource'] ?? null;

        if (!$resource) {
            throw new InvalidArgumentException('The "resource" parameter is required.');
        }
        $request = $context['request'];
        $query = $this->filterService->filterWithTFilter(request: $request, isCategoryFilter: $isCategoryFilter);

        $filterObjects = [];

        $locale = $request->get('locale') ?? "en_US";
        $objects = $query->find();
        $filters = $this->filterService->getAvailableFilters($resource);
        foreach ($filters as $filter) {
            $values = [];
            foreach ($objects as $item) {
                $possibleValues = $filter->getValue($item,$locale);
                if (!$possibleValues){
                    continue;
                }
                foreach ($possibleValues as $value) {
                    $values [] = $value;
                }
            }
            if ($filter instanceof PriceFilter && count($values) > 0){
                $values = [
                    'min' => min($values),
                    'max' => max($values)
                ];
            }
            if (!$filter instanceof PriceFilter){
                $values = array_intersect_key($values, array_unique(array_column($values, 'id')));
            }
            $id = null;
            $isVisible = true;
            $position = null;
            $choiceFilters = [];

            if ($isCategoryFilter) {
                $categoryId = null;
                foreach (CategoryFilter::getFilterName() as $filterName) {
                    $tfilters = $request->get('tfilters', []);
                    if (!isset($tfilters[$filterName])) {
                        continue;
                    }
                    $categoryId = $tfilters[$filterName];
                }
                if ($categoryId) {
                    $choiceFilters = ChoiceFilterQuery::create()->filterByCategoryId($categoryId)->find()->getData();
                }
                foreach ($choiceFilters as $choiceFilter) {
                    $otherType = $choiceFilter->getChoiceFilterOther()?->getType();
                    if (in_array($otherType, $filter->getFilterName(), true)) {
                        $isVisible = $choiceFilter->isVisible();
                        $position = $choiceFilter->getPosition();
                    }
                    if ($filter instanceof TheliaChoiceFilterInterface && !empty($item)) {
                        $mainType = $filter->getChoiceFilterType($item);
                        $id = $mainType->getId();
                        if ($choiceFilter->getAttribute() instanceof $mainType && $choiceFilter->getAttribute()->getId() === $mainType->getId()) {
                            $isVisible = $choiceFilter->isVisible();
                            $position = $choiceFilter->getPosition();
                        }
                        if ($choiceFilter->getFeature() instanceof $mainType && $choiceFilter->getFeature()->getId() === $mainType->getId()) {
                            $isVisible = $choiceFilter->isVisible();
                            $position = $choiceFilter->getPosition();
                        }
                    }
                }
            }
            $filterObjects[] = (new Filter())
                ->setId($id)
                ->setTitle($filter->getFilterName()[0])
                ->setType($filter->getFilterName()[0])
                ->setInputType('checkbox')
                ->setPosition($position)
                ->setVisible($isVisible)
                ->setValues($values);
        }

        foreach ($filterObjects as $filterObject) {
            if ($filterObject->getPosition() === null) {
                $allPosition = array_map(static function ($filterObject) {
                    return $filterObject->getPosition();
                }, $filterObjects);
                $max = max($allPosition);
                $filterObject->setPosition($max + 1);
            }
        }
        usort($filterObjects, static function ($a, $b) {
            return $a->getPosition() <=> $b->getPosition();
        });
        return $filterObjects;
    }
}
