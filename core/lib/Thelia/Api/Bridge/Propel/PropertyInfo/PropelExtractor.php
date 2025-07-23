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

namespace Thelia\Api\Bridge\Propel\PropertyInfo;

use Propel\Runtime\Collection\Collection;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Thelia\Api\Bridge\Propel\Attribute\Relation;

class PropelExtractor implements PropertyListExtractorInterface, PropertyTypeExtractorInterface, PropertyAccessExtractorInterface
{
    public function isReadable(string $class, string $property, array $context = []): null
    {
        return null;
    }

    public function isWritable(string $class, string $property, array $context = []): null
    {
        return null;
    }

    public function getProperties(string $class, array $context = []): null
    {
        return null;
    }

    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        /** @var \Reflector $reflector */
        $reflector = new \ReflectionClass($class);

        if (!$reflector->hasProperty($property)) {
            return null;
        }

        $reflectionProperty = $reflector->getProperty($property);

        foreach ($reflectionProperty->getAttributes(Relation::class) as $relationAttribute) {
            $targetClass = $relationAttribute->getArguments()['targetResource'];

            if ('array' === $reflectionProperty->getType()->getName() || \in_array(\Traversable::class, class_implements($reflectionProperty->getType()->getName()), true)) {
                return [
                    new Type(
                        Type::BUILTIN_TYPE_OBJECT,
                        false,
                        Collection::class,
                        true,
                        new Type(Type::BUILTIN_TYPE_INT),
                        new Type(
                            Type::BUILTIN_TYPE_OBJECT,
                            $reflectionProperty->getType()->allowsNull(),
                            $targetClass,
                        ),
                    ),
                ];
            }

            return [
                new Type(
                    Type::BUILTIN_TYPE_OBJECT,
                    $reflectionProperty->getType()->allowsNull(),
                    $targetClass,
                ),
            ];
        }

        return null;
    }
}
