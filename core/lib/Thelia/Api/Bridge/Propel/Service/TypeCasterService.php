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

namespace Thelia\Api\Bridge\Propel\Service;

class TypeCasterService
{
    public function castValueForSetter(object $object, string $setterName, mixed $value): mixed
    {
        try {
            $reflection = new \ReflectionMethod($object, $setterName);
            $parameters = $reflection->getParameters();

            if (empty($parameters)) {
                return $value;
            }

            $firstParameter = $parameters[0];
            $parameterType = $firstParameter->getType();

            if (!$parameterType instanceof \ReflectionType) {
                return $value;
            }

            if (null === $value && $parameterType->allowsNull()) {
                return null;
            }

            if (null === $value && !$parameterType->allowsNull()) {
                return $this->getDefaultValueForType($parameterType);
            }

            return $this->castValueToType($value, $parameterType);
        } catch (\ReflectionException) {
            return $value;
        }
    }

    private function castValueToType(mixed $value, \ReflectionType $type): mixed
    {
        if ($type instanceof \ReflectionNamedType) {
            $typeName = $type->getName();

            // Types primitifs
            return match ($typeName) {
                'string' => (string) $value,
                'int' => (int) $value,
                'float' => (float) $value,
                'bool' => (bool) $value,
                'array' => \is_array($value) ? $value : [$value],
                default => $value,
            };
        }

        return $value;
    }

    private function getDefaultValueForType(\ReflectionType $type): mixed
    {
        if ($type instanceof \ReflectionNamedType) {
            return match ($type->getName()) {
                'string' => '',
                'int' => 0,
                'float' => 0.0,
                'bool' => false,
                'array' => [],
                default => null,
            };
        }

        return null;
    }
}
