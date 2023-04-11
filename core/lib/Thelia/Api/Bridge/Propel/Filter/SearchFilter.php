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

namespace Thelia\Api\Bridge\Propel\Filter;

use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;

final class SearchFilter extends AbstractSearchFilter implements FilterInterface
{
    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if (
            null === $value ||
            !$this->isPropertyEnabled($property, $resourceClass)
        ) {
            return;
        }

        $values = $this->normalizeValues((array) $value, $property);

        if (null === $values) {
            return;
        }

        $strategy = $this->properties[$property] ?? self::STRATEGY_EXACT;

        $fieldPath = $this->getPropertyQueryPath($query, $property);

        $this->addWhereByStrategy(
            strategy: $strategy,
            query:  $query,
            fieldPath: $fieldPath,
            values:  $values
        );
    }

    /**
     * Adds where clause according to the strategy.
     *
     * @throws InvalidArgumentException If strategy does not exist
     */
    protected function addWhereByStrategy(string $strategy, ModelCriteria $query, string $fieldPath, mixed $values): void
    {
        if (!\is_array($values)) {
            $values = [$values];
        }

        if (!$strategy || self::STRATEGY_EXACT === $strategy) {
            $query->addUsingOperator(
                $fieldPath,
                1 === \count($values) ? $values[0]: $values,
                1 === \count($values) ? Criteria::EQUAL: Criteria::IN
            );

            return;
        }

        $conditions = [];

        foreach ($values as $key => $value) {
            $conditionName = "cond_" . $key;
            switch ($strategy) {
                case self::STRATEGY_PARTIAL:
                    $query->addCond($conditionName, $fieldPath, '%' . $value . '%', Criteria::LIKE);
                    break;
                case self::STRATEGY_START:
                    $query->addCond($conditionName, $fieldPath, $value . '%', Criteria::LIKE);
                    break;
                case self::STRATEGY_END:
                    $query->addCond($conditionName, $fieldPath, '%' . $value, Criteria::LIKE);
                    break;
                case self::STRATEGY_WORD_START:
                    $query->addCond('first_world', $fieldPath,$value . '%', Criteria::LIKE);
                    $query->addCond('other_worlds', $fieldPath,'% ' . $value . '%', Criteria::LIKE);
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
        return $this->getSearchDescription($resourceClass);
    }
}
