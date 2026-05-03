<?php

declare(strict_types=1);

namespace App\OpenApi;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;

final class ResponseShapeResolver
{
    /**
     * @var array<string, string|null>
     */
    private array $repositoryEntityCache = [];

    /**
     * @var array<string, array<string, string>>
     */
    private array $useMapCache = [];

    /**
     * @var array<string, array<string, mixed>|null>
     */
    private array $methodShapeCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(?Expr $expr, ReflectionMethod $reflectionMethod, ClassMethod $classMethod): ?array
    {
        if ($expr === null) {
            return null;
        }

        return match (true) {
            $expr instanceof Array_ => $this->resolveArrayShape($expr, $reflectionMethod, $classMethod),
            $expr instanceof Coalesce => $this->mergeShapes(
                $this->resolve($expr->left, $reflectionMethod, $classMethod),
                $this->resolve($expr->right, $reflectionMethod, $classMethod),
            ),
            $expr instanceof Variable => $this->resolveVariableShape($expr, $reflectionMethod, $classMethod),
            $expr instanceof PropertyFetch => $this->resolvePropertyFetchShape($expr, $reflectionMethod, $classMethod),
            $expr instanceof MethodCall => $this->resolveMethodCallShape($expr, $reflectionMethod, $classMethod),
            $expr instanceof New_ => $this->resolveNewShape($expr, $reflectionMethod),
            $expr instanceof String_ => ['kind' => 'scalar', 'type' => 'string', 'nullable' => false],
            $expr instanceof Int_ => ['kind' => 'scalar', 'type' => 'integer', 'nullable' => false],
            $expr instanceof DNumber => ['kind' => 'scalar', 'type' => 'number', 'nullable' => false],
            $expr instanceof ConstFetch => $this->resolveConstFetchShape($expr),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveNewShape(New_ $newExpr, ReflectionMethod $reflectionMethod): ?array
    {
        if (!$newExpr->class instanceof Name) {
            return null;
        }

        $className = $this->resolveImportedClassName($reflectionMethod->getDeclaringClass(), $newExpr->class);

        if ($className === null || !class_exists($className)) {
            return null;
        }

        return ['kind' => 'model', 'class' => $className, 'collection' => false, 'nullable' => false];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveArrayShape(Array_ $expr, ReflectionMethod $reflectionMethod, ClassMethod $classMethod): ?array
    {
        $isObject = false;
        $properties = [];
        $itemShape = null;

        foreach ($expr->items as $item) {
            if ($item === null) {
                continue;
            }

            if ($item->key !== null) {
                $isObject = true;
                $key = $item->key instanceof String_ ? $item->key->value : ($item->key instanceof Int_ ? (string) $item->key->value : null);

                if ($key === null) {
                    continue;
                }

                $properties[$key] = $this->resolve($item->value, $reflectionMethod, $classMethod) ?? ['kind' => 'scalar', 'type' => 'string', 'nullable' => false];

                continue;
            }

            $itemShape = $this->mergeShapes($itemShape, $this->resolve($item->value, $reflectionMethod, $classMethod));
        }

        if ($isObject) {
            return ['kind' => 'object', 'properties' => $properties];
        }

        return ['kind' => 'array', 'items' => $itemShape];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveVariableShape(Variable $variable, ReflectionMethod $reflectionMethod, ClassMethod $classMethod): ?array
    {
        if (!is_string($variable->name)) {
            return null;
        }

        foreach ($reflectionMethod->getParameters() as $parameter) {
            if ($parameter->getName() === $variable->name) {
                return $this->resolveTypeShape($parameter->getType());
            }
        }

        $assignmentShape = null;

        foreach (array_reverse((new NodeFinder())->findInstanceOf($classMethod->stmts ?? [], Assign::class)) as $assignment) {
            if (!$assignment instanceof Assign || !$assignment->var instanceof Variable || $assignment->var->name !== $variable->name) {
                continue;
            }

            $assignmentShape = $this->resolve($assignment->expr, $reflectionMethod, $classMethod);

            break;
        }

        foreach ((new NodeFinder())->findInstanceOf($classMethod->stmts ?? [], Instanceof_::class) as $instanceof) {
            if (!$instanceof instanceof Instanceof_ || !$instanceof->expr instanceof Variable || $instanceof->expr->name !== $variable->name || !$instanceof->class instanceof Name) {
                continue;
            }

            $className = $this->resolveImportedClassName($reflectionMethod->getDeclaringClass(), $instanceof->class);

            if ($className !== null && class_exists($className)) {
                return ['kind' => 'model', 'class' => $className, 'collection' => false, 'nullable' => false];
            }
        }

        return $assignmentShape;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePropertyFetchShape(PropertyFetch $propertyFetch, ReflectionMethod $reflectionMethod, ClassMethod $classMethod): ?array
    {
        if (!$propertyFetch->name instanceof Identifier) {
            return null;
        }

        $propertyName = $propertyFetch->name->toString();

        if ($propertyFetch->var instanceof Variable && $propertyFetch->var->name === 'this') {
            $propertyClass = $this->resolveThisPropertyClass($reflectionMethod->getDeclaringClass(), $propertyName);

            if ($propertyClass !== null && class_exists($propertyClass)) {
                return ['kind' => 'model', 'class' => $propertyClass, 'collection' => false, 'nullable' => false];
            }

            return null;
        }

        $ownerShape = $this->resolve($propertyFetch->var, $reflectionMethod, $classMethod);
        $ownerClass = is_string($ownerShape['class'] ?? null) && ($ownerShape['collection'] ?? false) === false
            ? $ownerShape['class']
            : null;

        if ($ownerClass === null || !class_exists($ownerClass)) {
            return null;
        }

        if ($this->supportsDoctrineMetadata($ownerClass)) {
            $metadata = $this->entityManager->getClassMetadata($ownerClass);

            if ($metadata->hasAssociation($propertyName)) {
                $targetClass = $metadata->getAssociationTargetClass($propertyName);

                return [
                    'kind' => 'model',
                    'class' => $targetClass,
                    'collection' => $metadata->isCollectionValuedAssociation($propertyName),
                    'nullable' => !$metadata->isCollectionValuedAssociation($propertyName) && $this->isAssociationNullable($metadata, $propertyName),
                ];
            }

            if ($metadata->hasField($propertyName)) {
                return $this->resolveFieldShape($metadata, $propertyName);
            }
        }

        $reflectionProperty = $this->findProperty($ownerClass, $propertyName);

        return $reflectionProperty !== null ? $this->resolveTypeShape($reflectionProperty->getType()) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveMethodCallShape(MethodCall $methodCall, ReflectionMethod $reflectionMethod, ClassMethod $classMethod): ?array
    {
        if (!$methodCall->name instanceof Identifier) {
            return null;
        }

        $methodName = $methodCall->name->toString();
        $ownerClass = $this->resolveMethodCallOwnerClass($methodCall, $reflectionMethod, $classMethod);

        if ($ownerClass === null || !class_exists($ownerClass)) {
            return null;
        }

        if (is_subclass_of($ownerClass, 'Doctrine\\Bundle\\DoctrineBundle\\Repository\\ServiceEntityRepository')) {
            $repositoryEntityClass = $this->resolveRepositoryEntityClass($ownerClass);

            if ($repositoryEntityClass !== null) {
                return match ($methodName) {
                    'find', 'findOneBy' => ['kind' => 'model', 'class' => $repositoryEntityClass, 'collection' => false, 'nullable' => true],
                    'findBy', 'findAll' => ['kind' => 'model', 'class' => $repositoryEntityClass, 'collection' => true],
                    default => $this->resolveInvokedMethodShape($ownerClass, $methodName),
                };
            }
        }

        return $this->resolveInvokedMethodShape($ownerClass, $methodName);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveInvokedMethodShape(string $class, string $methodName): ?array
    {
        $cacheKey = $class.'::'.$methodName;

        if (array_key_exists($cacheKey, $this->methodShapeCache)) {
            return $this->methodShapeCache[$cacheKey];
        }

        if (!method_exists($class, $methodName)) {
            return $this->methodShapeCache[$cacheKey] = null;
        }

        $method = new ReflectionMethod($class, $methodName);
        $shape = $this->resolveTypeShape($method->getReturnType());
        $docShape = $this->resolveDocblockReturnShape($method->getDocComment() ?: '', $class);

        return $this->methodShapeCache[$cacheKey] = $this->mergeShapes($shape, $docShape);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDocblockReturnShape(string $docComment, string $contextClass): ?array
    {
        if (!preg_match('/@return\s+([^\s]+)/', $docComment, $matches)) {
            return null;
        }

        $type = trim($matches[1]);

        if (str_contains($type, '|')) {
            $parts = array_filter(array_map('trim', explode('|', $type)));
            $shape = null;

            foreach ($parts as $part) {
                $shape = $this->mergeShapes($shape, $this->resolveDocblockTypeToken($part, $contextClass));
            }

            return $shape;
        }

        return $this->resolveDocblockTypeToken($type, $contextClass);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDocblockTypeToken(string $type, string $contextClass): ?array
    {
        $type = trim($type, "\\ \t\n\r\0\x0B");

        if (preg_match('/^(?:list|array|iterable)<(.+)>$/', $type, $matches)) {
            $itemShape = $this->resolveDocblockTypeToken(trim($matches[1]), $contextClass);

            if (($itemShape['kind'] ?? null) === 'model' && is_string($itemShape['class'] ?? null)) {
                return ['kind' => 'model', 'class' => $itemShape['class'], 'collection' => true];
            }

            return ['kind' => 'array', 'items' => $itemShape];
        }

        if (preg_match('/^(.+)\[\]$/', $type, $matches)) {
            $itemShape = $this->resolveDocblockTypeToken(trim($matches[1]), $contextClass);

            if (($itemShape['kind'] ?? null) === 'model' && is_string($itemShape['class'] ?? null)) {
                return ['kind' => 'model', 'class' => $itemShape['class'], 'collection' => true];
            }

            return ['kind' => 'array', 'items' => $itemShape];
        }

        return match ($type) {
            'int' => ['kind' => 'scalar', 'type' => 'integer', 'nullable' => false],
            'float' => ['kind' => 'scalar', 'type' => 'number', 'nullable' => false],
            'bool' => ['kind' => 'scalar', 'type' => 'boolean', 'nullable' => false],
            'string' => ['kind' => 'scalar', 'type' => 'string', 'nullable' => false],
            'array', 'iterable' => ['kind' => 'array', 'items' => null],
            default => ($resolved = $this->resolveImportedClassName(new ReflectionClass($contextClass), new Name($type))) !== null && class_exists($resolved)
                ? ['kind' => 'model', 'class' => $resolved, 'collection' => false, 'nullable' => false]
                : null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveTypeShape(?ReflectionType $type): ?array
    {
        if (!$type instanceof ReflectionNamedType) {
            return null;
        }

        $nullable = $type->allowsNull();
        $typeName = $type->getName();

        if ($type->isBuiltin()) {
            return match ($typeName) {
                'int' => ['kind' => 'scalar', 'type' => 'integer', 'nullable' => $nullable],
                'float' => ['kind' => 'scalar', 'type' => 'number', 'nullable' => $nullable],
                'bool' => ['kind' => 'scalar', 'type' => 'boolean', 'nullable' => $nullable],
                'string' => ['kind' => 'scalar', 'type' => 'string', 'nullable' => $nullable],
                'array', 'iterable' => ['kind' => 'array', 'items' => null, 'nullable' => $nullable],
                default => null,
            };
        }

        if (enum_exists($typeName)) {
            return ['kind' => 'scalar', 'type' => 'string', 'nullable' => $nullable];
        }

        if (class_exists($typeName)) {
            return ['kind' => 'model', 'class' => $typeName, 'collection' => false, 'nullable' => $nullable];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveFieldShape(ClassMetadata $metadata, string $fieldName): ?array
    {
        return match ($metadata->getTypeOfField($fieldName)) {
            'boolean' => ['kind' => 'scalar', 'type' => 'boolean', 'nullable' => false],
            'integer', 'smallint', 'bigint' => ['kind' => 'scalar', 'type' => 'integer', 'nullable' => false],
            'float' => ['kind' => 'scalar', 'type' => 'number', 'nullable' => false],
            'array', 'json' => ['kind' => 'array', 'items' => null],
            default => ['kind' => 'scalar', 'type' => 'string', 'nullable' => false],
        };
    }

    private function resolveMethodCallOwnerClass(MethodCall $methodCall, ReflectionMethod $reflectionMethod, ClassMethod $classMethod): ?string
    {
        if ($methodCall->var instanceof Variable && $methodCall->var->name === 'this') {
            return $reflectionMethod->getDeclaringClass()->getName();
        }

        if ($methodCall->var instanceof PropertyFetch && $methodCall->var->var instanceof Variable && $methodCall->var->var->name === 'this' && $methodCall->var->name instanceof Identifier) {
            return $this->resolveThisPropertyClass($reflectionMethod->getDeclaringClass(), $methodCall->var->name->toString());
        }

        $ownerShape = $this->resolve($methodCall->var, $reflectionMethod, $classMethod);

        return is_string($ownerShape['class'] ?? null) && ($ownerShape['collection'] ?? false) === false
            ? $ownerShape['class']
            : null;
    }

    private function resolveThisPropertyClass(ReflectionClass $reflectionClass, string $propertyName): ?string
    {
        $property = $this->findProperty($reflectionClass->getName(), $propertyName);

        if ($property === null) {
            return null;
        }

        $type = $property->getType();

        return $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
    }

    private function findProperty(string $class, string $propertyName): ?ReflectionProperty
    {
        $reflectionClass = new ReflectionClass($class);

        while ($reflectionClass) {
            if ($reflectionClass->hasProperty($propertyName)) {
                return $reflectionClass->getProperty($propertyName);
            }

            $reflectionClass = $reflectionClass->getParentClass() ?: null;
        }

        return null;
    }

    private function resolveRepositoryEntityClass(string $repositoryClass): ?string
    {
        if (array_key_exists($repositoryClass, $this->repositoryEntityCache)) {
            return $this->repositoryEntityCache[$repositoryClass];
        }

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata instanceof ClassMetadata && $metadata->customRepositoryClassName === $repositoryClass) {
                return $this->repositoryEntityCache[$repositoryClass] = $metadata->getName();
            }
        }

        return $this->repositoryEntityCache[$repositoryClass] = null;
    }

    private function supportsDoctrineMetadata(string $class): bool
    {
        try {
            return $this->entityManager->getClassMetadata($class) instanceof ClassMetadata;
        } catch (\Throwable) {
            return false;
        }
    }

    private function isAssociationNullable(ClassMetadata $metadata, string $associationName): bool
    {
        $mapping = $metadata->getAssociationMapping($associationName);

        if (!isset($mapping['joinColumns'][0]['nullable'])) {
            $type = $metadata->getReflectionProperty($associationName)?->getType();

            return $type instanceof ReflectionNamedType ? $type->allowsNull() : false;
        }

        return (bool) $mapping['joinColumns'][0]['nullable'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveConstFetchShape(ConstFetch $constFetch): ?array
    {
        return match (strtolower($constFetch->name->toString())) {
            'true', 'false' => ['kind' => 'scalar', 'type' => 'boolean', 'nullable' => false],
            'null' => ['kind' => 'scalar', 'type' => 'string', 'nullable' => true],
            default => null,
        };
    }

    private function resolveImportedClassName(ReflectionClass $reflectionClass, Name $name): ?string
    {
        if ($name->isFullyQualified()) {
            return $name->toString();
        }

        $firstSegment = $name->getFirst();
        $useMap = $this->loadUseMap($reflectionClass);

        if (isset($useMap[$firstSegment])) {
            $suffixName = $name->slice(1);
            $suffix = $suffixName?->toString() ?? '';

            return $suffix !== ''
                ? $useMap[$firstSegment].'\\'.$suffix
                : $useMap[$firstSegment];
        }

        $candidate = $reflectionClass->getNamespaceName().'\\'.$name->toString();

        if (class_exists($candidate)) {
            return $candidate;
        }

        if (class_exists($name->toString())) {
            return $name->toString();
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function loadUseMap(ReflectionClass $reflectionClass): array
    {
        $cacheKey = $reflectionClass->getName();

        if (isset($this->useMapCache[$cacheKey])) {
            return $this->useMapCache[$cacheKey];
        }

        $fileName = $reflectionClass->getFileName();

        if (!is_string($fileName) || !is_file($fileName)) {
            return $this->useMapCache[$cacheKey] = [];
        }

        $source = file_get_contents($fileName);

        if (!is_string($source)) {
            return $this->useMapCache[$cacheKey] = [];
        }

        preg_match_all('/^use\s+([^;]+?)(?:\s+as\s+(\w+))?;$/m', $source, $matches, PREG_SET_ORDER);

        $useMap = [];

        foreach ($matches as $match) {
            $import = trim($match[1]);
            $alias = $match[2] ?? basename(str_replace('\\', '/', $import));
            $useMap[$alias] = $import;
        }

        return $this->useMapCache[$cacheKey] = $useMap;
    }

    /**
     * @param array<string, mixed>|null $left
     * @param array<string, mixed>|null $right
     *
     * @return array<string, mixed>|null
     */
    private function mergeShapes(?array $left, ?array $right): ?array
    {
        if ($left === null) {
            return $right;
        }

        if ($right === null) {
            return $left;
        }

        if (($left['kind'] ?? null) === 'array' && ($right['kind'] ?? null) === 'array') {
            return [
                'kind' => 'array',
                'items' => $this->mergeShapes($left['items'] ?? null, $right['items'] ?? null),
            ];
        }

        if (($left['kind'] ?? null) === 'object' && ($right['kind'] ?? null) === 'object') {
            return [
                'kind' => 'object',
                'properties' => array_merge($left['properties'] ?? [], $right['properties'] ?? []),
            ];
        }

        if (($left['kind'] ?? null) === 'model' && ($left['collection'] ?? false) === true && ($right['kind'] ?? null) === 'array') {
            return $left;
        }

        if (($right['kind'] ?? null) === 'model' && ($right['collection'] ?? false) === true && ($left['kind'] ?? null) === 'array') {
            return $right;
        }

        if (($left['kind'] ?? null) === 'model' && ($right['kind'] ?? null) === 'model' && ($left['class'] ?? null) === ($right['class'] ?? null) && ($left['collection'] ?? null) === ($right['collection'] ?? null)) {
            return $left;
        }

        if (($left['kind'] ?? null) === 'scalar' && ($right['kind'] ?? null) === 'scalar' && ($left['type'] ?? null) === ($right['type'] ?? null)) {
            return ['kind' => 'scalar', 'type' => $left['type'], 'nullable' => (bool) (($left['nullable'] ?? false) || ($right['nullable'] ?? false))];
        }

        if (($left['kind'] ?? null) === 'array' && ($left['items'] ?? null) === null) {
            return $right;
        }

        if (($right['kind'] ?? null) === 'array' && ($right['items'] ?? null) === null) {
            return $left;
        }

        return $left;
    }
}
