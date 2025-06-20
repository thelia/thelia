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
namespace Thelia\Api\Bridge\Propel\Filter;

use ReflectionProperty;
use DateTimeInterface;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;

class DateFilter extends AbstractFilter
{
    public const PARAMETER_BEFORE = 'before';

    public const PARAMETER_STRICTLY_BEFORE = 'strictly_before';

    public const PARAMETER_AFTER = 'after';

    public const PARAMETER_STRICTLY_AFTER = 'strictly_after';

    public const EXCLUDE_NULL = 'exclude_null';

    public const INCLUDE_NULL_BEFORE = 'include_null_before';

    public const INCLUDE_NULL_AFTER = 'include_null_after';

    public const INCLUDE_NULL_BEFORE_AND_AFTER = 'include_null_before_and_after';

    protected function filterProperty(string $property, $values, ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if (
            null === $values
            || !$this->isPropertyEnabled($property, $resourceClass)
        ) {
            return;
        }

        $strategy = $this->getProperties()[$property] ?? self::EXCLUDE_NULL;

        $conditions = [];
        $fieldPath = $this->getPropertyQueryPath($query, $property, $context);
        foreach ($values as $key => $value) {
            $conditionName = 'cond_'.$key;
            switch ($key) {
                case self::PARAMETER_BEFORE:
                    $query->addCond($conditionName, $fieldPath, $value, Criteria::LESS_EQUAL);
                    break;
                case self::PARAMETER_STRICTLY_BEFORE:
                    $query->addCond($conditionName, $fieldPath, $value, Criteria::LESS_THAN);
                    break;
                case self::PARAMETER_AFTER:
                    $query->addCond($conditionName, $fieldPath, $value, Criteria::GREATER_EQUAL);
                    break;
                case self::PARAMETER_STRICTLY_AFTER:
                    $query->addCond($conditionName, $fieldPath, $value, Criteria::GREATER_THAN);
                    break;
                default:
                    continue 2;
            }

            $conditions[] = $conditionName;
        }

        $query->combine($conditions);

        switch ($strategy) {
            case self::EXCLUDE_NULL:
                $query->addAnd($fieldPath, null, Criteria::NOT_EQUAL);
                break;
            case self::INCLUDE_NULL_BEFORE_AND_AFTER:
                $query->addOr($fieldPath, null, Criteria::EQUAL);
                break;
            case self::INCLUDE_NULL_AFTER:
                $query->addOr($fieldPath, null, Criteria::EQUAL);
                $query->orderBy($fieldPath, Criteria::DESC);
                // no break
            case self::INCLUDE_NULL_BEFORE:
                $query->addOr($fieldPath, null, Criteria::EQUAL);
                $query->orderBy($fieldPath);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        $filterProperties = $this->getProperties();
        if (null === $filterProperties) {
            return [];
        }

        $description = [];
        foreach (array_keys($filterProperties) as $property) {
            $propertyName = $this->normalizePropertyName($property);

            $reflectionProperty = $this->getReflectionProperty($propertyName, $resourceClass);
            if (!$reflectionProperty instanceof ReflectionProperty) {
                continue;
            }

            $description += $this->getFilterDescription($propertyName, self::PARAMETER_BEFORE);
            $description += $this->getFilterDescription($propertyName, self::PARAMETER_STRICTLY_BEFORE);
            $description += $this->getFilterDescription($propertyName, self::PARAMETER_AFTER);
            $description += $this->getFilterDescription($propertyName, self::PARAMETER_STRICTLY_AFTER);
        }

        return $description;
    }

    /**
     * Gets filter description.
     */
    protected function getFilterDescription(string $property, string $period): array
    {
        $propertyName = $this->normalizePropertyName($property);

        return [
            sprintf('%s[%s]', $propertyName, $period) => [
                'property' => $propertyName,
                'type' => DateTimeInterface::class,
                'required' => false,
            ],
        ];
    }
}
