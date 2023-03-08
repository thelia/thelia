<?php

declare(strict_types=1);

namespace Thelia\Api\Bridge\Propel\Filter;

use ApiPlatform\Doctrine\Common\Filter\BooleanFilterTrait;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Propel\Runtime\ActiveQuery\ModelCriteria;

final class BooleanFilter extends AbstractFilter implements FilterInterface
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

        $filterMethod = "filterBy".ucfirst($property);
        $query->$filterMethod(filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $filterProperties = $this->getProperties();
        if (null === $filterProperties) {
            return [];
        }

        $classProperties = array_reduce(
            (new \ReflectionClass($resourceClass))->getProperties(),
            function ($carry, \ReflectionProperty $property) {
                $carry[$property->getName()] = $property;
                return $carry;
            },
        );

        foreach ($filterProperties as $property => $strategy) {
            $propertyName = $this->normalizePropertyName($property);

            $reflectionProperty = $classProperties[$propertyName]?? null;
            if (null === $reflectionProperty) {
                //Todo search for relation
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
