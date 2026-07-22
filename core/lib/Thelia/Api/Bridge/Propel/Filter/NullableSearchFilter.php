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

namespace Thelia\Api\Bridge\Propel\Filter;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;

/**
 * Exact-match filter that also accepts the literal value "null" to match SQL NULL.
 *
 * e.g. ?redirected=5 -> redirected = 5 ; ?redirected=null -> redirected IS NULL.
 */
class NullableSearchFilter extends AbstractFilter
{
    public const NULL_VALUE = 'null';

    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if (null === $value || !$this->isPropertyEnabled($property, $resourceClass)) {
            return;
        }

        if (!property_exists($resourceClass, $property)) {
            throw new \RuntimeException(sprintf('Property "%s" does not exist in class "%s".', $property, $resourceClass));
        }

        $column = ucfirst($property);

        if (self::NULL_VALUE === $value || '' === $value) {
            $query->filterBy($column, null, Criteria::ISNULL);

            return;
        }

        if (\is_array($value)) {
            $query->filterBy($column, $value, Criteria::IN);

            return;
        }

        $query->filterBy($column, $value, Criteria::EQUAL);
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $filterProperties = $this->getProperties();
        if (null === $filterProperties) {
            return [];
        }

        foreach ($filterProperties as $property => $propertyOptions) {
            $propertyName = $this->normalizePropertyName($property);
            $description[$propertyName] = [
                'property' => $propertyName,
                'type' => 'string',
                'required' => false,
                'description' => sprintf('Filter "%s" by exact value, or use "null" to match entries with no value.', $propertyName),
            ];
        }

        return $description;
    }
}
