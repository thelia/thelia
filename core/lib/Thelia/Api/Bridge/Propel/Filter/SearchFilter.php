<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
use ReflectionNamedType;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Resource\TranslatableResourceInterface;

final class SearchFilter extends AbstractFilter
{
    /**
     * @var string Exact matching
     */
    public const STRATEGY_EXACT = 'exact';

    /**
     * @var string The value must be contained in the field
     */
    public const STRATEGY_PARTIAL = 'partial';

    /**
     * @var string Finds fields that are starting with the value
     */
    public const STRATEGY_START = 'start';

    /**
     * @var string Finds fields that are ending with the value
     */
    public const STRATEGY_END = 'end';

    /**
     * @var string Finds fields that are starting with the word
     */
    public const STRATEGY_WORD_START = 'word_start';

    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if (
            null === $value
            || !$this->isPropertyEnabled($property, $resourceClass)
        ) {
            return;
        }

        $values = $this->normalizeValues((array) $value, $property);

        if (null === $values) {
            return;
        }

        $strategy = $this->properties[$property] ?? self::STRATEGY_EXACT;
        $fieldPath = $this->getPropertyQueryPath($query, $property, $context);

        if (\is_array($this->properties[$property])) {
            $strategy = $this->properties[$property]['strategy'];
            $fieldPath = $this->properties[$property]['fieldPath'];
        }

        $this->addWhereByStrategy(
            strategy: $strategy,
            query: $query,
            fieldPath: $fieldPath,
            values: $values
        );
    }

    /**
     * Adds where clause according to the strategy.
     *
     * @throws InvalidArgumentException If strategy does not exist
     */
    private function addWhereByStrategy(string $strategy, ModelCriteria $query, string $fieldPath, mixed $values): void
    {
        if (!\is_array($values)) {
            $values = [$values];
        }

        if (!$strategy || self::STRATEGY_EXACT === $strategy) {
            $query->addUsingOperator(
                $fieldPath,
                1 === \count($values) ? $values[0] : $values,
                1 === \count($values) ? Criteria::EQUAL : Criteria::IN
            );

            return;
        }

        $conditions = [];

        foreach ($values as $key => $value) {
            $conditionName = 'cond_'.$key;
            switch ($strategy) {
                case self::STRATEGY_PARTIAL:
                    $query->addCond($conditionName, $fieldPath, '%'.$value.'%', Criteria::LIKE);
                    break;
                case self::STRATEGY_START:
                    $query->addCond($conditionName, $fieldPath, $value.'%', Criteria::LIKE);
                    break;
                case self::STRATEGY_END:
                    $query->addCond($conditionName, $fieldPath, '%'.$value, Criteria::LIKE);
                    break;
                case self::STRATEGY_WORD_START:
                    $query->addCond('first_world', $fieldPath, $value.'%', Criteria::LIKE);
                    $query->addCond('other_worlds', $fieldPath, '% '.$value.'%', Criteria::LIKE);
                    $query->combine(['first_world', 'other_worlds'], Criteria::LOGICAL_OR, $conditionName);
                    break;
                default:
                    continue 2;
            }

            $conditions[] = $conditionName;
        }

        $query->combine($conditions, Criteria::LOGICAL_OR);
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $filterProperties = $this->getProperties();
        if (null === $filterProperties) {
            return [];
        }

        foreach ($filterProperties as $property => $strategy) {
            $propertyName = $this->normalizePropertyName($property);
            $isLocalized = false;

            $reflectionProperty = $this->getReflectionProperty($propertyName, $resourceClass);

            if (!$reflectionProperty instanceof ReflectionProperty && is_subclass_of($resourceClass, TranslatableResourceInterface::class)) {
                $isLocalized = true;
                $reflectionProperty = $this->getReflectionProperty($propertyName, $resourceClass::getI18nResourceClass());
            }

            if (!$reflectionProperty instanceof ReflectionProperty) {
                continue;
            }

            $strategy = $this->getProperties()[$property] ?? self::STRATEGY_EXACT;
            $filterParameterNames = [$propertyName];

            if (self::STRATEGY_EXACT === $strategy) {
                $filterParameterNames[] = $propertyName.'[]';
            }

            foreach ($filterParameterNames as $filterParameterName) {
                $description[$filterParameterName] = [
                    'property' => $propertyName,
                    'type' => $this->getType($reflectionProperty->getType()),
                    'required' => false,
                    'strategy' => $strategy,
                    'is_collection' => str_ends_with($filterParameterName, '[]'),
                ];
                if ($isLocalized) {
                    $description['locale'] = [
                        'property' => 'locale',
                        'description' => 'Locale used to filter localized fields, if empty default lang will be used',
                        'type' => 'string',
                        'required' => false,
                        'is_collection' => false,
                    ];
                }
            }
        }

        return $description;
    }

    private function normalizeValues(array $values, string $property): ?array
    {
        foreach ($values as $key => $value) {
            if (!\is_int($key) || !\is_string($value) && !\is_int($value)) {
                unset($values[$key]);
            }
        }

        if ($values === []) {
            $this->getLogger()->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('At least one value is required, multiple values should be in "%1$s[]=firstvalue&%1$s[]=secondvalue" format', $property)),
            ]);

            return null;
        }

        return array_values($values);
    }

    private function getType(ReflectionNamedType $type): string
    {
        if (!$type->isBuiltin()) {
            return 'string';
        }

        return $type->getName();
    }
}
