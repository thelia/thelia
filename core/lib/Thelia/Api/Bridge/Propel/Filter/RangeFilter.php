<?php

namespace Thelia\Api\Bridge\Propel\Filter;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;

class RangeFilter extends AbstractFilter
{
    public const PARAMETER_GREATER_THAN = 'gt';
    public const PARAMETER_GREATER_THAN_OR_EQUAL = 'gte';
    public const PARAMETER_LESS_THAN = 'lt';
    public const PARAMETER_LESS_THAN_OR_EQUAL = 'lte';

    protected function filterProperty(string $property, $values, ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if (
            null === $values ||
            !$this->isPropertyEnabled($property, $resourceClass)
        ) {
            return;
        }
        $conditions = [];
        $fieldPath = $this->getPropertyQueryPath($query, $property, $context);
        foreach ($values as $key => $value) {
            $conditionName = "cond_" . $key;
            switch ($key) {
                case self::PARAMETER_GREATER_THAN:
                    $query->addCond($conditionName, $fieldPath, $value, Criteria::GREATER_THAN);
                    break;
                case self::PARAMETER_GREATER_THAN_OR_EQUAL:
                    $query->addCond($conditionName, $fieldPath, $value, Criteria::GREATER_EQUAL);
                    break;
                case self::PARAMETER_LESS_THAN:
                    $query->addCond($conditionName, $fieldPath,$value, Criteria::LESS_THAN);
                    break;
                case self::PARAMETER_LESS_THAN_OR_EQUAL:
                    $query->addCond($conditionName, $fieldPath, $value, Criteria::LESS_EQUAL);
                    break;
                default:
                    continue 2;
            }
            $conditions[] = $conditionName;
        }
        $query->combine($conditions, Criteria::LOGICAL_AND);
    }


    public function getDescription(string $resourceClass): array
    {
        $filterProperties = $this->getProperties();
        if (null === $filterProperties) {
            return [];
        }
        $description = [];
        foreach ($filterProperties as $property => $strategy) {
            $propertyName = $this->normalizePropertyName($property);

            $reflectionProperty = $this->getReflectionProperty($propertyName, $resourceClass);
            if (null === $reflectionProperty) {
                continue;
            }
            $description += $this->getFilterDescription($propertyName, self::PARAMETER_GREATER_THAN);
            $description += $this->getFilterDescription($propertyName, self::PARAMETER_GREATER_THAN_OR_EQUAL);
            $description += $this->getFilterDescription($propertyName, self::PARAMETER_LESS_THAN);
            $description += $this->getFilterDescription($propertyName, self::PARAMETER_LESS_THAN_OR_EQUAL);

        }
        return $description;
    }

    /**
     * Gets filter description.
     */
    private function getFilterDescription(string $fieldName, string $operator): array
    {
        $propertyName = $this->normalizePropertyName($fieldName);

        return [
            sprintf('%s[%s]', $propertyName, $operator) => [
                'property' => $propertyName,
                'type' => 'string',
                'required' => false,
            ],
        ];
    }
}

