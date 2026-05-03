<?php

namespace App\Model\Request;

use BackedEnum;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

final class RequestModelHydrator
{
    public static function hydrate(string $class, array $values): object
    {
        $reflectionClass = new ReflectionClass($class);
        $model = $reflectionClass->newInstance();

        foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!array_key_exists($property->getName(), $values)) {
                continue;
            }

            $property->setValue(
                $model,
                self::normalizeValue($values[$property->getName()], $property->getType())
            );
        }

        return $model;
    }

    private static function normalizeValue(mixed $value, ?ReflectionType $type): mixed
    {
        if (null === $value || null === $type) {
            return $value;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $innerType) {
                if ($innerType instanceof ReflectionNamedType && 'null' === $innerType->getName()) {
                    continue;
                }

                return self::normalizeValue($value, $innerType);
            }

            return $value;
        }

        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        if (!$type->isBuiltin()) {
            $typeName = $type->getName();

            if (enum_exists($typeName) && is_subclass_of($typeName, BackedEnum::class)) {
                return $typeName::from($value);
            }

            return $value;
        }

        return match ($type->getName()) {
            'array' => is_array($value) ? $value : [$value],
            'bool' => self::normalizeBoolean($value),
            'float' => (float) $value,
            'int' => (int) $value,
            'string' => (string) $value,
            default => $value,
        };
    }

    private static function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
