<?php

declare(strict_types=1);

namespace App\OpenApi;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use OpenApi\Annotations as OA;
use OpenApi\Generator;
use Symfony\Component\Serializer\Annotation as Serializer;

final class RequestSchemaFactory
{
    /**
     * @var array<string, list<string>>
     */
    private array $lifecycleManagedFields = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function supports(string $class): bool
    {
        try {
            return $this->entityManager->getClassMetadata($class) instanceof ClassMetadata;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array{
     *     httpMethod?: string,
     *     lookupFields?: list<string>,
     *     lookupInPath?: bool
     * } $context
     */
    public function describe(OA\Schema $schema, string $class, array $context = []): void
    {
        $this->describeClassSchema($schema, $class, [
            'httpMethod' => strtoupper($context['httpMethod'] ?? 'POST'),
            'lookupFields' => array_values(array_filter($context['lookupFields'] ?? [], 'is_string')),
            'lookupInPath' => (bool) ($context['lookupInPath'] ?? false),
            'topLevel' => true,
            'skipAssociation' => null,
            'allowCollections' => true,
            'visited' => [],
        ]);
    }

    /**
     * @param array{
     *     httpMethod: string,
     *     lookupFields: list<string>,
     *     lookupInPath: bool,
     *     topLevel: bool,
     *     skipAssociation: ?string,
     *     allowCollections: bool,
     *     visited: list<string>
     * } $context
     */
    private function describeClassSchema(OA\Schema|OA\Items|OA\Property $schema, string $class, array $context): void
    {
        $metadata = $this->entityManager->getClassMetadata($class);
        $reflectionClass = $metadata->getReflectionClass();

        $schema->type = 'object';
        $schema->properties = [];

        $required = [];

        foreach ($this->collectMembers($class, $context) as $member) {
            $property = new OA\Property([
                'property' => $member['name'],
                '_context' => Util::createWeakContext($schema->_context),
            ]);

            if ('field' === $member['kind']) {
                $this->describeFieldProperty($property, $member['owner'], $member['name']);
            } else {
                $this->describeAssociationProperty($property, $member['owner'], $member['name'], $context);
            }

            $schema->properties[] = $property;

            if ($this->isRequiredMember($member, $context)) {
                $required[] = $member['name'];
            }
        }

        if ($metadata->discriminatorMap && isset($metadata->discriminatorColumn['name'])) {
            $propertyName = $metadata->discriminatorColumn['name'];

            if (!in_array($propertyName, array_map(static fn (OA\Property $property): string => $property->property, $schema->properties), true)) {
                $property = new OA\Property([
                    'property' => $propertyName,
                    'type' => 'string',
                    'enum' => array_keys($metadata->discriminatorMap),
                    '_context' => Util::createWeakContext($schema->_context),
                ]);
                $schema->properties[] = $property;
            }

            $required[] = $propertyName;
        }

        if ($required !== []) {
            $schema->required = array_values(array_unique($required));
        }
    }

    /**
     * @param array{
     *     httpMethod: string,
     *     lookupFields: list<string>,
     *     lookupInPath: bool,
     *     topLevel: bool,
     *     skipAssociation: ?string,
     *     allowCollections: bool,
     *     visited: list<string>
     * } $context
     *
     * @return list<array{kind: 'field'|'association', owner: string, name: string}>
     */
    private function collectMembers(string $class, array $context): array
    {
        $metadata = $this->entityManager->getClassMetadata($class);
        $classes = [$class];

        if ($metadata->discriminatorMap) {
            $classes = array_values(array_unique([...$classes, ...array_values($metadata->discriminatorMap)]));
        }

        $members = [];

        foreach ($classes as $memberClass) {
            $memberMetadata = $this->entityManager->getClassMetadata($memberClass);

            foreach ($memberMetadata->getFieldNames() as $fieldName) {
                if ($this->shouldIncludeField($memberMetadata, $fieldName, $context)) {
                    $members[$fieldName] ??= [
                        'kind' => 'field',
                        'owner' => $memberClass,
                        'name' => $fieldName,
                    ];
                }
            }

            foreach ($memberMetadata->getAssociationNames() as $associationName) {
                if ($this->shouldIncludeAssociation($memberMetadata, $associationName, $context)) {
                    $members[$associationName] ??= [
                        'kind' => 'association',
                        'owner' => $memberClass,
                        'name' => $associationName,
                    ];
                }
            }
        }

        return array_values($members);
    }

    /**
     * @param array{
     *     httpMethod: string,
     *     lookupFields: list<string>,
     *     lookupInPath: bool,
     *     topLevel: bool,
     *     skipAssociation: ?string,
     *     allowCollections: bool,
     *     visited: list<string>
     * } $context
     */
    private function shouldIncludeField(ClassMetadata $metadata, string $fieldName, array $context): bool
    {
        if (($metadata->discriminatorColumn['name'] ?? null) === $fieldName) {
            return false;
        }

        if (in_array($fieldName, $context['lookupFields'], true)) {
            return true;
        }

        if (in_array($fieldName, $metadata->getIdentifierFieldNames(), true)) {
            return !$context['topLevel'];
        }

        if ($this->isLifecycleManagedField($metadata->getName(), $fieldName)) {
            return false;
        }

        return $this->hasRequestSignal($metadata->getName(), $fieldName);
    }

    /**
     * @param array{
     *     httpMethod: string,
     *     lookupFields: list<string>,
     *     lookupInPath: bool,
     *     topLevel: bool,
     *     skipAssociation: ?string,
     *     allowCollections: bool,
     *     visited: list<string>
     * } $context
     */
    private function shouldIncludeAssociation(ClassMetadata $metadata, string $associationName, array $context): bool
    {
        if ($associationName === $context['skipAssociation']) {
            return false;
        }

        if ($metadata->isCollectionValuedAssociation($associationName) && !$context['allowCollections']) {
            return false;
        }

        if ($metadata->isSingleValuedAssociation($associationName) && $metadata->isAssociationInverseSide($associationName)) {
            return false;
        }

        if (in_array($associationName, $context['lookupFields'], true)) {
            return true;
        }

        return $this->hasRequestSignal($metadata->getName(), $associationName);
    }

    private function describeFieldProperty(OA\Property $property, string $ownerClass, string $fieldName): void
    {
        $metadata = $this->entityManager->getClassMetadata($ownerClass);
        $reflectionProperty = $metadata->getReflectionProperty($fieldName);

        $this->describeFieldSchema(
            $property,
            $metadata->getTypeOfField($fieldName),
            $reflectionProperty?->getType(),
            $metadata->getFieldMapping($fieldName)['enumType'] ?? null,
            $this->findConstantEnumValues($metadata->getReflectionClass(), $fieldName),
        );
    }

    /**
     * @param array{
     *     httpMethod: string,
     *     lookupFields: list<string>,
     *     lookupInPath: bool,
     *     topLevel: bool,
     *     skipAssociation: ?string,
     *     allowCollections: bool,
     *     visited: list<string>
     * } $context
     */
    private function describeAssociationProperty(OA\Property $property, string $ownerClass, string $associationName, array $context): void
    {
        $metadata = $this->entityManager->getClassMetadata($ownerClass);
        $mapping = $metadata->getAssociationMapping($associationName);
        $targetClass = $metadata->getAssociationTargetClass($associationName);
        $nullable = $this->isAssociationNullable($metadata, $associationName);

        if ($metadata->isCollectionValuedAssociation($associationName)) {
            $property->type = 'array';
            $property->items = new OA\Items([
                '_context' => Util::createWeakContext($property->_context),
            ]);

            if ($this->isIdentifierCollection($mapping)) {
                $this->describeIdentifierSchema($property->items, $targetClass);

                return;
            }

            $nextContext = [
                ...$context,
                'topLevel' => false,
                'skipAssociation' => $mapping['mappedBy'] ?? null,
                'allowCollections' => !in_array($targetClass, $context['visited'], true),
                'visited' => [...$context['visited'], $ownerClass],
            ];
            $this->describeClassSchema($property->items, $targetClass, $nextContext);

            return;
        }

        if ($this->shouldInlineSingleAssociation($mapping, $targetClass, $context)) {
            $nextContext = [
                ...$context,
                'topLevel' => false,
                'skipAssociation' => $mapping['mappedBy'] ?? null,
                'allowCollections' => !in_array($targetClass, $context['visited'], true),
                'visited' => [...$context['visited'], $ownerClass],
            ];
            $this->describeClassSchema($property, $targetClass, $nextContext);
            $property->nullable = $nullable;

            return;
        }

        $this->describeIdentifierSchema($property, $targetClass);
        $property->nullable = $nullable;
    }

    private function describeIdentifierSchema(OA\Schema|OA\Items|OA\Property $schema, string $class): void
    {
        $metadata = $this->entityManager->getClassMetadata($class);
        $schema->type = 'object';
        $schema->properties = [];
        $schema->required = [];

        foreach ($metadata->getIdentifierFieldNames() as $identifier) {
            $property = new OA\Property([
                'property' => $identifier,
                '_context' => Util::createWeakContext($schema->_context),
            ]);

            if ($metadata->hasField($identifier)) {
                $reflectionProperty = $metadata->getReflectionProperty($identifier);
                $this->describeFieldSchema(
                    $property,
                    $metadata->getTypeOfField($identifier),
                    $reflectionProperty?->getType(),
                    $metadata->getFieldMapping($identifier)['enumType'] ?? null,
                    $this->findConstantEnumValues($metadata->getReflectionClass(), $identifier),
                );
            } else {
                $property->type = 'string';
            }

            $property->nullable = false;
            $schema->properties[] = $property;
            $schema->required[] = $identifier;
        }
    }

    /**
     * @param list<string>|null $inferredEnum
     */
    private function describeFieldSchema(
        OA\Schema|OA\Items|OA\Property $schema,
        ?string $doctrineType,
        ?\ReflectionType $reflectionType,
        ?string $enumClass = null,
        ?array $inferredEnum = null,
    ): void {
        if (null !== $enumClass && enum_exists($enumClass)) {
            $schema->type = 'string';
            $schema->enum = $this->getEnumValues($enumClass);
            $schema->nullable = $this->isNullableType($reflectionType);

            return;
        }

        if ($reflectionType instanceof \ReflectionNamedType && enum_exists($reflectionType->getName())) {
            $schema->type = 'string';
            $schema->enum = $this->getEnumValues($reflectionType->getName());
            $schema->nullable = $reflectionType->allowsNull();

            return;
        }

        match ($doctrineType) {
            'boolean' => $schema->type = 'boolean',
            'integer', 'smallint', 'bigint' => $schema->type = 'integer',
            'float' => $schema->type = 'number',
            'decimal' => $schema->type = 'string',
            'array', 'json' => $schema->type = 'array',
            'datetime', 'datetimetz' => [$schema->type, $schema->format] = ['string', 'date-time'],
            'date' => [$schema->type, $schema->format] = ['string', 'date'],
            'time' => [$schema->type, $schema->format] = ['string', 'time'],
            'uuid', 'guid' => [$schema->type, $schema->format] = ['string', 'uuid'],
            default => $this->describeTypedSchema($schema, $reflectionType),
        };

        if ('array' === ($schema->type ?? null) && (!isset($schema->items) || Generator::UNDEFINED === $schema->items)) {
            $schema->items = new OA\Items([
                '_context' => Util::createWeakContext($schema->_context),
            ]);
        }

        if ($inferredEnum !== null && $inferredEnum !== []) {
            $schema->enum = $inferredEnum;
        }

        $schema->nullable = $this->isNullableType($reflectionType);
    }

    private function describeTypedSchema(OA\Schema|OA\Items|OA\Property $schema, ?\ReflectionType $reflectionType): void
    {
        $typeName = $this->getNamedTypeName($reflectionType);

        match ($typeName) {
            'int' => $schema->type = 'integer',
            'float' => $schema->type = 'number',
            'bool' => $schema->type = 'boolean',
            'array', 'iterable' => $schema->type = 'array',
            'string' => $schema->type = 'string',
            default => $schema->type = 'string',
        };

        if ('array' === $schema->type) {
            $schema->items = new OA\Items([
                '_context' => Util::createWeakContext($schema->_context),
            ]);
        }

        $schema->nullable = $this->isNullableType($reflectionType);
    }

    /**
     * @param array{kind: 'field'|'association', owner: string, name: string} $member
     * @param array{
     *     httpMethod: string,
     *     lookupFields: list<string>,
     *     lookupInPath: bool,
     *     topLevel: bool,
     *     skipAssociation: ?string,
     *     allowCollections: bool,
     *     visited: list<string>
     * } $context
     */
    private function isRequiredMember(array $member, array $context): bool
    {
        $metadata = $this->entityManager->getClassMetadata($member['owner']);
        $name = $member['name'];

        if ($context['topLevel'] && in_array($name, $context['lookupFields'], true) && !$context['lookupInPath']) {
            return true;
        }

        if (in_array($name, $metadata->getIdentifierFieldNames(), true)) {
            return false;
        }

        if (!$context['topLevel'] && 'POST' !== $context['httpMethod']) {
            return false;
        }

        if ($context['topLevel'] && 'POST' !== $context['httpMethod']) {
            return false;
        }

        if ('association' === $member['kind']) {
            if ($metadata->isCollectionValuedAssociation($name)) {
                return false;
            }

            return !$this->isAssociationNullable($metadata, $name) && !$this->propertyHasDefaultValue($metadata->getName(), $name);
        }

        return !$this->isFieldNullable($metadata, $name) && !$this->propertyHasDefaultValue($metadata->getName(), $name);
    }

    /**
     * @param array{
     *     httpMethod: string,
     *     lookupFields: list<string>,
     *     lookupInPath: bool,
     *     topLevel: bool,
     *     skipAssociation: ?string,
     *     allowCollections: bool,
     *     visited: list<string>
     * } $context
     */
    private function shouldInlineSingleAssociation(array $mapping, string $targetClass, array $context): bool
    {
        if (!$context['topLevel'] || 'POST' !== $context['httpMethod']) {
            return false;
        }

        if (in_array($targetClass, $context['visited'], true)) {
            return false;
        }

        if (($mapping['type'] ?? null) !== ClassMetadata::ONE_TO_ONE) {
            return false;
        }

        if (($mapping['isOwningSide'] ?? false) !== true) {
            return false;
        }

        $cascade = array_map('strtolower', $mapping['cascade'] ?? []);

        return in_array('persist', $cascade, true) || in_array('all', $cascade, true);
    }

    private function isIdentifierCollection(array $mapping): bool
    {
        return ($mapping['type'] ?? null) === ClassMetadata::MANY_TO_MANY;
    }

    private function hasRequestSignal(string $class, string $propertyName): bool
    {
        $reflectionClass = new \ReflectionClass($class);

        if (!$reflectionClass->hasProperty($propertyName)) {
            return false;
        }

        $property = $reflectionClass->getProperty($propertyName);
        $hasSerializerGroups = false;
        $hasValidatorSignal = false;

        foreach ($property->getAttributes() as $attribute) {
            $attributeClass = $attribute->getName();

            if ($attributeClass === Serializer\Groups::class) {
                $hasSerializerGroups = true;
                continue;
            }

            if (str_starts_with($attributeClass, 'Symfony\\Component\\Validator\\Constraints\\')) {
                $hasValidatorSignal = true;
                continue;
            }

            if (str_starts_with($attributeClass, 'App\\Validator\\')) {
                $hasValidatorSignal = true;
            }
        }

        return $hasSerializerGroups || $hasValidatorSignal;
    }

    private function isLifecycleManagedField(string $class, string $fieldName): bool
    {
        if (!isset($this->lifecycleManagedFields[$class])) {
            $managedFields = [];
            $reflectionClass = new \ReflectionClass($class);

            foreach ($reflectionClass->getMethods() as $method) {
                if (!$this->isLifecycleCallback($method)) {
                    continue;
                }

                $code = $this->getMethodSource($method);

                if (preg_match_all('/\\$this->([A-Za-z_][A-Za-z0-9_]*)\\s*=/', $code, $matches)) {
                    foreach ($matches[1] as $managedField) {
                        $managedFields[$managedField] = true;
                    }
                }
            }

            $this->lifecycleManagedFields[$class] = array_keys($managedFields);
        }

        return in_array($fieldName, $this->lifecycleManagedFields[$class], true);
    }

    private function isLifecycleCallback(\ReflectionMethod $method): bool
    {
        foreach ($method->getAttributes() as $attribute) {
            if (in_array($attribute->getName(), [
                'Doctrine\\ORM\\Mapping\\PrePersist',
                'Doctrine\\ORM\\Mapping\\PreUpdate',
                'Doctrine\\ORM\\Mapping\\PreFlush',
            ], true)) {
                return true;
            }
        }

        return false;
    }

    private function getMethodSource(\ReflectionMethod $method): string
    {
        $file = $method->getFileName();

        if (!is_string($file) || !is_file($file)) {
            return '';
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return '';
        }

        return implode("\n", array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }

    /**
     * @return list<string>|null
     */
    private function findConstantEnumValues(\ReflectionClass $reflectionClass, string $fieldName): ?array
    {
        $normalized = strtoupper((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $fieldName));
        $constants = $reflectionClass->getConstants();

        $listConstantName = $normalized.'S';

        if (isset($constants[$listConstantName]) && is_array($constants[$listConstantName])) {
            $listValues = array_values(array_filter(
                $constants[$listConstantName],
                static fn (mixed $value): bool => is_string($value) || is_int($value)
            ));

            if ($listValues !== []) {
                return array_map(static fn (string|int $value): string => (string) $value, $listValues);
            }
        }

        $directPrefixValues = [];

        foreach ($constants as $name => $value) {
            if (!is_string($value) && !is_int($value)) {
                continue;
            }

            if (str_starts_with($name, $normalized.'_')) {
                $directPrefixValues[] = (string) $value;
            }
        }

        $directPrefixValues = array_values(array_unique($directPrefixValues));

        if (count($directPrefixValues) > 1) {
            return $directPrefixValues;
        }

        $indirectValues = [];

        foreach ($constants as $name => $value) {
            if ((!is_string($value) && !is_int($value)) || str_starts_with($name, $normalized.'_')) {
                continue;
            }

            if (str_contains($name, '_'.$normalized.'_') || str_ends_with($name, '_'.$normalized)) {
                $indirectValues[] = (string) $value;
            }
        }

        $indirectValues = array_values(array_unique($indirectValues));

        return count($indirectValues) > 1 ? $indirectValues : null;
    }

    private function propertyHasDefaultValue(string $class, string $propertyName): bool
    {
        $reflectionClass = new \ReflectionClass($class);

        if (!$reflectionClass->hasProperty($propertyName)) {
            return false;
        }

        return $reflectionClass->getProperty($propertyName)->hasDefaultValue();
    }

    private function isFieldNullable(ClassMetadata $metadata, string $fieldName): bool
    {
        $reflectionProperty = $metadata->getReflectionProperty($fieldName);

        if ($this->isNullableType($reflectionProperty?->getType())) {
            return true;
        }

        return (bool) ($metadata->getFieldMapping($fieldName)['nullable'] ?? false);
    }

    private function isNullableType(?\ReflectionType $reflectionType): bool
    {
        if ($reflectionType instanceof \ReflectionNamedType) {
            return $reflectionType->allowsNull();
        }

        if ($reflectionType instanceof \ReflectionUnionType) {
            foreach ($reflectionType->getTypes() as $type) {
                if ('null' === $type->getName()) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getNamedTypeName(?\ReflectionType $reflectionType): ?string
    {
        if ($reflectionType instanceof \ReflectionNamedType) {
            return $reflectionType->getName();
        }

        if ($reflectionType instanceof \ReflectionUnionType) {
            foreach ($reflectionType->getTypes() as $type) {
                if ('null' !== $type->getName()) {
                    return $type->getName();
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function getEnumValues(string $enumClass): array
    {
        return array_map(
            static fn (\UnitEnum $case): string => $case instanceof \BackedEnum ? (string) $case->value : $case->name,
            $enumClass::cases()
        );
    }

    private function isAssociationNullable(ClassMetadata $metadata, string $associationName): bool
    {
        $mapping = $metadata->getAssociationMapping($associationName);

        if (!isset($mapping['joinColumns'][0]['nullable'])) {
            $type = $metadata->getReflectionProperty($associationName)?->getType();

            return $this->isNullableType($type);
        }

        return true === $mapping['joinColumns'][0]['nullable'];
    }
}
