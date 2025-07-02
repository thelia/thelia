<?php

declare(strict_types=1);

namespace Thelia\Api\Bridge\Propel\Service;

use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;

class TypeCasterService
{

    public function castValueForSetter(object $object, string $setterName, mixed $value): mixed
    {
        try {
            $reflection = new ReflectionMethod($object, $setterName);
            $parameters = $reflection->getParameters();

            if (empty($parameters)) {
                return $value;
            }

            $firstParameter = $parameters[0];
            $parameterType = $firstParameter->getType();

            if (!$parameterType instanceof ReflectionType) {
                return $value;
            }

            if ($value === null && $parameterType->allowsNull()) {
                return null;
            }

            if ($value === null && !$parameterType->allowsNull()) {
                return $this->getDefaultValueForType($parameterType);
            }

            return $this->castValueToType($value, $parameterType);

        } catch (ReflectionException) {
            return $value;
        }
    }

    private function castValueToType(mixed $value, ReflectionType $type): mixed
    {
        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();

            // Types primitifs
            return match ($typeName) {
                'string' => (string) $value,
                'int' => (int) $value,
                'float' => (float) $value,
                'bool' => (bool) $value,
                'array' => is_array($value) ? $value : [$value],
                default => $value,
            };
        }

        return $value;
    }

    private function getDefaultValueForType(ReflectionType $type): mixed
    {
        if ($type instanceof ReflectionNamedType) {
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
