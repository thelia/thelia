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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Model\Lang;

abstract class AbstractFilter implements FilterInterface
{
    protected LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger = null,
        protected ?array $properties = null,
        protected ?NameConverterInterface $nameConverter = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function apply(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        foreach ($context['filters'] as $property => $value) {
            $this->filterProperty($this->denormalizePropertyName($property), $value, $query, $resourceClass, $operation, $context);
        }
    }

    /**
     * Passes a property through the filter.
     */
    abstract protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void;

    protected function getProperties(): ?array
    {
        return $this->properties;
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Determines whether the given property is enabled.
     */
    protected function isPropertyEnabled(string $property, string $resourceClass): bool
    {
        if (null === $this->properties) {
            return false;
        }

        return \array_key_exists($property, $this->properties);
    }

    protected function denormalizePropertyName(string|int $property): string
    {
        if (!$this->nameConverter instanceof NameConverterInterface) {
            return $property;
        }

        return implode('.', array_map($this->nameConverter->denormalize(...), explode('.', (string) $property)));
    }

    protected function normalizePropertyName(string $property): string
    {
        if (!$this->nameConverter instanceof NameConverterInterface) {
            return $property;
        }

        return implode('.', array_map($this->nameConverter->normalize(...), explode('.', $property)));
    }

    protected function getReflectionProperty(string $propertyName, string $class): ?\ReflectionProperty
    {
        $propertyParts = explode('.', $propertyName);

        $classProperties = array_reduce(
            (new \ReflectionClass($class))->getProperties(),
            function ($carry, \ReflectionProperty $property) {
                $carry[$property->getName()] = $property;

                return $carry;
            },
        );

        if (\count($propertyParts) > 1) {
            /** @var \ReflectionProperty $relationProperty */
            $relationProperty = $classProperties[$propertyParts[0]] ?? null;

            if (null === $relationProperty) {
                return null;
            }

            foreach ($relationProperty->getAttributes(Relation::class) as $relationAttribute) {
                $targetClass = $relationAttribute->getArguments()['targetResource'];
                if (null === $targetClass) {
                    continue;
                }

                $subPropertyName = substr($propertyName, strpos($propertyName, '.') + 1);
                $reflectionProperty = $this->getReflectionProperty($subPropertyName, $targetClass);

                if (null !== $reflectionProperty) {
                    return $reflectionProperty;
                }
            }
        }

        return $classProperties[$propertyName] ?? null;
    }

    protected function getPropertyQueryPath(
        ModelCriteria $query,
        string $property,
        array $context
    ): string {
        $resourceClass = $context['resource_class'];
        // Check if we are on a localized field
        if (!str_contains($property, '.') && !property_exists($resourceClass, $property) && is_subclass_of($resourceClass, TranslatableResourceInterface::class)) {
            return $this->getLocalizedPropertyQueryPath($query, $property, $context);
        }

        $fieldPath = $query->getTableMap()->getName().'.'.$property;
        // Replace all dot by underscore to build relation alias
        $fieldPath = str_replace('.', '_', $fieldPath);

        // Replace last underscore by a dot, because after last underscore it's the mysql field
        $lastUnderscorePosition = strrpos($fieldPath, '_');
        if ($lastUnderscorePosition !== false) {
            $fieldPath[$lastUnderscorePosition] = '.';
        }

        $tableAlias = strtok($fieldPath, '.');

        // Transform php field to DB column name
        $field = strtok('');
        $column = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $field));

        return strtolower($tableAlias.'.'.$column);
    }

    protected function getLocalizedPropertyQueryPath(
        ModelCriteria $query,
        string $property,
        array $context,
    ) {
        $locale = $context['filters']['locale'] ?? Lang::getDefaultLanguage()->getLocale();

        return str_replace("_", "", $query->getTableMap()->getName()).'_lang_'.$locale.'.'.$property;
    }
}
