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

final class BooleanFilter extends AbstractFilter implements FilterInterface
{
    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if (
            null === $value
            || !$this->isPropertyEnabled($property, $resourceClass)
        ) {
            return;
        }

        $fieldPath = $this->getPropertyQueryPath($query, $property, $context);

        $query->where($fieldPath.' = ?', filter_var($value, \FILTER_VALIDATE_BOOLEAN));
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

            $reflectionProperty = $this->getReflectionProperty($propertyName, $resourceClass);
            if (null === $reflectionProperty) {
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
