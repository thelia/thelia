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

use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;

class OrderFilter extends AbstractFilter
{
    public const DIRECTION_ASC = 'ASC';

    public const DIRECTION_DESC = 'DESC';

    public function apply(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if (!isset($context['filters']['order']) || !\is_array($context['filters']['order'])) {
            parent::apply($query, $resourceClass, $operation, $context);

            return;
        }

        foreach ($context['filters']['order'] as $property => $value) {
            $this->filterProperty($this->denormalizePropertyName($property), $value, $query, $resourceClass, $operation, $context);
        }
    }

    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if (
            !$this->isPropertyEnabled($property, $resourceClass)
        ) {
            return;
        }

        $direction = $this->normalizeValue($value, $property);
        if (null === $direction) {
            return;
        }

        $fieldPath = $this->getPropertyQueryPath($query, $property, $context);

        if (\is_array($this->properties[$property])) {
            $fieldPath = $this->properties[$property]['fieldPath'];
        }

        $query->orderBy($fieldPath, $direction);
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
            $description[sprintf('%s[%s]', 'order', $propertyName)] = [
                'property' => $propertyName,
                'type' => 'string',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'enum' => [
                        strtolower(OrderFilterInterface::DIRECTION_ASC),
                        strtolower(OrderFilterInterface::DIRECTION_DESC),
                    ],
                ],
            ];
        }

        return $description;
    }

    private function normalizeValue($value, string $property): ?string
    {
        if (empty($value) && null !== $defaultDirection = $this->getProperties()[$property]['default_direction'] ?? null) {
            // fallback to default direction
            $value = $defaultDirection;
        }

        $value = strtoupper((string) $value);
        if (!\in_array($value, [self::DIRECTION_ASC, self::DIRECTION_DESC], true)) {
            return null;
        }

        return $value;
    }
}
