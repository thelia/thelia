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

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;

class BooleanFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (
            null === $value
            || !$this->isPropertyEnabled($property, $resourceClass)
        ) {
            return;
        }

        $query->where($this->fieldPathOf($property, $query, $context).' = ?', filter_var($value, \FILTER_VALIDATE_BOOLEAN));
    }

    protected function fieldPathOf(string $property, ModelCriteria $query, array $context): string
    {
        if (\is_array($this->properties[$property])) {
            return $this->properties[$property]['fieldPath'];
        }

        return $this->getPropertyQueryPath($query, $property, $context);
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $filterProperties = $this->getProperties();

        if (null === $filterProperties) {
            return [];
        }

        foreach (array_keys($filterProperties) as $property) {
            $propertyName = $this->normalizePropertyName($property);

            $reflectionProperty = $this->getReflectionProperty($propertyName, $resourceClass);

            if (!$reflectionProperty instanceof \ReflectionProperty) {
                continue;
            }

            $description[$propertyName] = [
                'property' => $propertyName,
                'type' => 'bool',
                'required' => false,
            ];
        }

        return $description;
    }
}
