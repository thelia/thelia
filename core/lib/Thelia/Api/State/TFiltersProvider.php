<?php

namespace Thelia\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use InvalidArgumentException;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\CategoryFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaChoiceFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Resource\Filter;
use Thelia\Model\ChoiceFilterQuery;
use Symfony\Component\HttpFoundation\RequestStack;

class TFiltersProvider implements ProviderInterface
{
    public function __construct(protected FilterService $filterService,private readonly RequestStack $requestStack)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $resource = $uriVariables['resource'] ?? null;

        if (!$resource) {
            throw new InvalidArgumentException('The "resource" parameter is required.');
        }
        $request = $this->requestStack->getCurrentRequest();
        $isApiRoute = $request->get('isApiRoute',false);
        if ($isApiRoute){
            $query = $this->filterService->filterTFilterWithRequest(request: $request, isCategoryFilter: $isCategoryFilter);
        }
        if (!$isApiRoute){
            $query = $this->filterService->filterTFilterWithContext(context: $context, isCategoryFilter: $isCategoryFilter);
        }

        $filterObjects = [];
        $locale = null;
        if ($request){
            $locale = $request->get('locale');
        }
        if (isset($context["filters"]["locale"])){
            $locale = $context["filters"]["locale"];
        }
        $locale = $locale ?? "en_US";
        $objects = $query->find();
        $filters = $this->filterService->getAvailableFilters($resource);
        foreach ($filters as $filter) {
            $values = [];
            $hasMain = false;
            foreach ($objects as $item) {
                $possibleValues = $filter->getValue($item,$locale);
                if (!$possibleValues){
                    continue;
                }
                foreach ($possibleValues as $value) {
                    $values [] = $value;
                }
            }
            if ((isset($values[0]["mainId"]))){
                $hasMain = true;
                $values = array_intersect_key($values, array_unique(array_map(
                    static function($item) {
                        return $item['id'] . '-' . $item['mainId'];
                    },
                    $values
                )));
            }else{
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
            if ($hasMain){
                $splitValues = [];
                foreach ($values as $value){
                    $splitValues[$value['mainId']][] = $value;
                }
                foreach ($splitValues as $value){
                    $filterDto = new Filter();
                    $filterDto
                        ->setId($value[0]['mainId'])
                        ->setTitle($value[0]['mainTitle'])
                        ->setType($filter->getFilterName()[0])
                        ->setInputType('checkbox')
                        ->setPosition($position)
                        ->setVisible($isVisible);
                    $value = array_map(function ($val){
                        unset($val['mainId'], $val['mainTitle']);
                        return $val;
                    },$value);
                    $filterDto->setValues($value);
                    $filterObjects[] = $filterDto;
                }
            }else {
                $filterObjects[] = (new Filter())
                    ->setId($id)
                    ->setTitle($filter->getFilterName()[0])
                    ->setType($filter->getFilterName()[0])
                    ->setInputType('checkbox')
                    ->setPosition($position)
                    ->setVisible($isVisible)
                    ->setValues(array_values($values));
            }
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
