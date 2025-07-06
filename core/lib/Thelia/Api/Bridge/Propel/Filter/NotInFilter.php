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
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;

class NotInFilter extends AbstractFilter
{
    public function apply(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (!isset($context['filters']['not_in']) || !\is_array($context['filters']['not_in'])) {
            parent::apply($query, $resourceClass, $operation, $context);

            return;
        }

        foreach ($context['filters']['not_in'] as $property => $value) {
            $this->filterProperty($this->denormalizePropertyName($property), $value, $query, $resourceClass, $operation, $context);
        }
    }

    protected function filterProperty(
        string $property,
        $value,
        ModelCriteria $query,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (!isset($context['filters']['not_in']) || !$this->isPropertyEnabled($property, $resourceClass)) {
            return;
        }

        if (\is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        if (!\is_array($value)) {
            throw new \InvalidArgumentException(\sprintf('The "NotIn" filter expects an array for the property "%s".', $property));
        }

        if (!property_exists($resourceClass, $property)) {
            throw new \RuntimeException(\sprintf('Property "%s" does not exist in class "%s".', $property, $resourceClass));
        }

        $property = ucfirst($property);
        $query->filterBy($property, $value, Criteria::NOT_IN);
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
            $description[\sprintf('%s[%s]', 'not_in', $propertyName)] = [
                'property' => $propertyName,
                'type' => 'array',
                'required' => false,
                'description' => \sprintf('Exclude specified values for property "%s".', $propertyName),
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'array',
                    ],
                ],
            ];
        }

        return $description;
    }
}
