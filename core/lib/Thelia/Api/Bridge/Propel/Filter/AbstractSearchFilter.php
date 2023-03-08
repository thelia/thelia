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

use ApiPlatform\Api\IdentifiersExtractorInterface;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Thelia\Api\Bridge\Propel\Attribute\Relation;

abstract class AbstractSearchFilter extends AbstractFilter implements FilterInterface
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

    private IriConverterInterface $iriConverter;
    private \Symfony\Component\PropertyAccess\PropertyAccessor|PropertyAccessorInterface $propertyAccessor;

    public function __construct(IriConverterInterface $iriConverter, PropertyAccessorInterface $propertyAccessor = null, LoggerInterface $logger = null, array $properties = null, IdentifiersExtractorInterface $identifiersExtractor = null, NameConverterInterface $nameConverter = null)
    {
        parent::__construct($logger, $properties, $nameConverter);

        $this->iriConverter = $iriConverter;
        $this->identifiersExtractor = $identifiersExtractor;
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    protected function getIriConverter(): IriConverterInterface
    {
        return $this->iriConverter;
    }

    protected function getPropertyAccessor(): PropertyAccessorInterface
    {
        return $this->propertyAccessor;
    }


    /**
     * {@inheritdoc}
     */
    protected function getType(\ReflectionNamedType $type): string
    {
        if (!$type->isBuiltin()) {
            return 'string';
        }
        return $type->getName();
    }

    protected function normalizeValues(array $values, string $property): ?array
    {
        foreach ($values as $key => $value) {
            if (!\is_int($key) || !(\is_string($value) || \is_int($value))) {
                unset($values[$key]);
            }
        }

        if (empty($values)) {
            $this->getLogger()->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('At least one value is required, multiple values should be in "%1$s[]=firstvalue&%1$s[]=secondvalue" format', $property)),
            ]);

            return null;
        }

        return array_values($values);
    }

    public function getSearchDescription(string $resourceClass, bool $forceString = false): array
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

            $strategy = $this->getProperties()[$property] ?? self::STRATEGY_EXACT;
            $filterParameterNames = [$propertyName];

            if (self::STRATEGY_EXACT === $strategy) {
                $filterParameterNames[] = $propertyName.'[]';
            }

            foreach ($filterParameterNames as $filterParameterName) {
                $description[$filterParameterName] = [
                    'property' => $propertyName,
                    'type' => $forceString ? 'string' : $this->getType($reflectionProperty->getType()),
                    'required' => false,
                    'strategy' => $strategy,
                    'is_collection' => str_ends_with((string) $filterParameterName, '[]'),
                ];
            }
        }

        return $description;
    }

    /**
     * Search a reflection property in class and his relation for a given property name
     */
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


        if (count($propertyParts) > 1) {
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

                $subPropertyName = substr($propertyName, strpos($propertyName, ".") + 1);
                $reflectionProperty = $this->getReflectionProperty($subPropertyName, $targetClass);

                if (null !== $reflectionProperty) {
                    return $reflectionProperty;
                }
            }
        }

        return $classProperties[$propertyName]?? null;
    }
}
