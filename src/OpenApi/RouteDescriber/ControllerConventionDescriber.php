<?php

declare(strict_types=1);

namespace App\OpenApi\RouteDescriber;

use App\Controller\Login;
use App\Model\Request\LoginRequestModel;
use App\Model\Response\AuthenticationFailureResponse;
use App\Model\Response\LoginSuccessResponse;
use App\OpenApi\OpenApiHelper;
use App\OpenApi\ResponseShapeResolver;
use App\Serializer\Groups;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberInterface;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberTrait;
use Nelmio\ApiDocBundle\Util\LegacyTypeConverter;
use OpenApi\Annotations as OA;
use OpenApi\Generator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\Routing\Route;

final class ControllerConventionDescriber implements RouteDescriberInterface, ModelRegistryAwareInterface
{
    use ModelRegistryAwareTrait;
    use RouteDescriberTrait;

    public const CONTEXT_LOOKUP_ONLY_ENTITY_ARGUMENT = 'app_lookup_only_entity_argument';
    public const CONTEXT_ROUTE_PLACEHOLDERS = 'app_route_placeholders';

    /**
     * @var array<string, ClassMethod|null>
     */
    private array $methodCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ResponseShapeResolver $responseShapeResolver,
    ) {}

    public function describe(OA\OpenApi $api, Route $route, ReflectionMethod $reflectionMethod): void
    {
        $classMethod = $this->loadClassMethodNode($reflectionMethod);
        $routePlaceholders = $this->extractRoutePlaceholders($route->getPath());
        $lookupOnlyEntityArgument = $this->isLookupOnlyEntityArgumentAction($routePlaceholders, $reflectionMethod, $classMethod);

        foreach ($this->getOperations($api, $route) as $operation) {
            $this->applyOperationMetadata($operation, $route, $reflectionMethod, $routePlaceholders, $lookupOnlyEntityArgument);
            $this->applySecurity($operation, $route, $classMethod);

            if ($reflectionMethod->getDeclaringClass()->getName() === Login::class) {
                $this->addJsonRequestBody($operation, LoginRequestModel::class);
                $this->addJsonModelResponse($operation, 200, LoginSuccessResponse::class, 'JWT created');
                $this->addJsonModelResponse($operation, 401, AuthenticationFailureResponse::class, 'Authentication failed');

                continue;
            }

            if ($lookupOnlyEntityArgument) {
                $operation->requestBody = Generator::UNDEFINED;
            }

            $this->applySuccessResponse($operation, $reflectionMethod, $classMethod);
            $this->applyErrorResponses($operation, $classMethod);
        }
    }

    /**
     * @param list<string> $routePlaceholders
     */
    private function applyOperationMetadata(
        OA\Operation $operation,
        Route $route,
        ReflectionMethod $reflectionMethod,
        array $routePlaceholders,
        bool $lookupOnlyEntityArgument,
    ): void {
        if (Generator::isDefault($operation->operationId)) {
            $operation->operationId = lcfirst($reflectionMethod->getDeclaringClass()->getShortName());
        }

        if (Generator::isDefault($operation->summary)) {
            $operation->summary = strtolower(trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $reflectionMethod->getDeclaringClass()->getShortName())));
        }

        if (Generator::isDefault($operation->tags)) {
            $operation->tags = [$this->guessTag($route->getPath())];
        }

        $operation->_context->{self::CONTEXT_ROUTE_PLACEHOLDERS} = $routePlaceholders;
        $operation->_context->{self::CONTEXT_LOOKUP_ONLY_ENTITY_ARGUMENT} = $lookupOnlyEntityArgument;
    }

    private function applySecurity(OA\Operation $operation, Route $route, ?ClassMethod $classMethod): void
    {
        if (!Generator::isDefault($operation->security)) {
            return;
        }

        if (str_starts_with($route->getPath(), '/pull/')) {
            $operation->security = [['PullToken' => []]];

            return;
        }

        if ($classMethod !== null && $this->requiresAuthenticatedUser($classMethod)) {
            $operation->security = [['Bearer' => []]];

            return;
        }

        $operation->security = [];
    }

    private function applySuccessResponse(OA\Operation $operation, ReflectionMethod $reflectionMethod, ?ClassMethod $classMethod): void
    {
        if ($classMethod === null) {
            return;
        }

        foreach ($this->findReturnNodes($classMethod) as $returnNode) {
            $expr = $returnNode->expr;

            if ($expr instanceof MethodCall && $this->isNamedMethodCall($expr, 'buildSerializedResponse')) {
                $this->applySerializedResponse($operation, $reflectionMethod, $classMethod, $expr);

                return;
            }
        }

        foreach ($this->findReturnNodes($classMethod) as $returnNode) {
            $expr = $returnNode->expr;

            if ($expr instanceof New_ && $this->isNewClass($expr, 'JsonResponse')) {
                $this->applyJsonResponse($operation, $reflectionMethod, $classMethod, $expr);

                return;
            }

            if ($expr instanceof New_ && $this->isNewClass($expr, 'Response')) {
                $this->applyHttpResponse($operation, $expr);

                return;
            }
        }
    }

    private function applySerializedResponse(
        OA\Operation $operation,
        ReflectionMethod $reflectionMethod,
        ClassMethod $classMethod,
        MethodCall $call,
    ): void {
        $statusCode = $this->extractCallStatusCode($call, 200);
        [$groupName, $groups] = $this->extractGroupInfo($call);

        $shape = $this->responseShapeResolver->resolve($call->args[0]->value ?? null, $reflectionMethod, $classMethod);
        $shape = $this->applyGroupFallback($shape, $groupName);

        $this->addShapeResponse($operation, $statusCode, $shape, 'Successful response', $groups);
    }

    private function applyJsonResponse(
        OA\Operation $operation,
        ReflectionMethod $reflectionMethod,
        ClassMethod $classMethod,
        New_ $newResponse,
    ): void {
        $statusCode = $this->extractStatusCode($newResponse, 200);

        if (!isset($newResponse->args[0])) {
            $this->addShapeResponse($operation, $statusCode, ['kind' => 'object', 'properties' => []], 'Successful response');

            return;
        }

        $shape = $this->responseShapeResolver->resolve($newResponse->args[0]->value, $reflectionMethod, $classMethod);
        $this->addShapeResponse($operation, $statusCode, $shape, 'Successful response');
    }

    private function applyHttpResponse(OA\Operation $operation, New_ $newResponse): void
    {
        $statusCode = $this->extractStatusCode($newResponse, 200);
        $this->addEmptyResponse($operation, $statusCode, 204 === $statusCode ? 'No content' : 'Successful response');
    }

    private function applyErrorResponses(OA\Operation $operation, ?ClassMethod $classMethod): void
    {
        if ($classMethod === null) {
            return;
        }

        if ($this->hasMethodCall($classMethod, 'validate')) {
            $this->addEmptyResponse($operation, 400, 'Bad request');
        }

        if ($this->hasMethodCall($classMethod, 'denyAccessUnlessGranted')) {
            $this->addEmptyResponse($operation, 403, 'Forbidden');
        }

        foreach ($this->findThrowNodes($classMethod) as $throwNode) {
            $exceptionClass = $this->resolveThrownClass($throwNode);

            match ($exceptionClass) {
                'ConflictHttpException' => $this->addEmptyResponse($operation, 409, 'Conflict'),
                'NotFoundHttpException' => $this->addEmptyResponse($operation, 404, 'Not found'),
                'UnauthorizedHttpException' => $this->addEmptyResponse($operation, 401, 'Unauthorized'),
                'UnprocessableEntityHttpException' => $this->addEmptyResponse($operation, 422, 'Unprocessable entity'),
                default => null,
            };
        }
    }

    /**
     * @param list<string>|null $groups
     * @param array<string, mixed>|null $shape
     */
    private function addShapeResponse(
        OA\Operation $operation,
        int $statusCode,
        ?array $shape,
        string $description,
        ?array $groups = null,
    ): void {
        if ($shape === null || ($shape['kind'] ?? null) === 'empty') {
            $this->addEmptyResponse($operation, $statusCode, $description);

            return;
        }

        /** @var OA\Response $response */
        $response = Util::getIndexedCollectionItem($operation, OA\Response::class, (string) $statusCode);
        $response->description = $description;

        $schema = OpenApiHelper::getJsonResponseSchema($response);
        $this->applyShapeToSchema($schema, $shape, $groups);
    }

    /**
     * @param list<string>|null $groups
     * @param array<string, mixed> $shape
     */
    private function applyShapeToSchema(OA\Schema|OA\Property|OA\Items $schema, array $shape, ?array $groups = null): void
    {
        $kind = $shape['kind'] ?? null;

        if ($kind === 'model' && is_string($shape['class'] ?? null)) {
            $modelRef = $this->modelRegistry->register(new Model(
                LegacyTypeConverter::createType($shape['class']),
                groups: $groups
            ));

            if ($schema instanceof OA\Schema) {
                OpenApiHelper::applyModelReference($schema, $modelRef, (bool) ($shape['collection'] ?? false));
            } elseif (($shape['collection'] ?? false) === true) {
                $schema->type = 'array';
                $schema->items = new OA\Items([
                    'ref' => $modelRef,
                    '_context' => Util::createWeakContext($schema->_context),
                ]);
            } else {
                $schema->ref = $modelRef;
            }

            return;
        }

        if ($kind === 'array') {
            $schema->type = 'array';
            $schema->items = new OA\Items([
                '_context' => Util::createWeakContext($schema->_context),
            ]);

            if (is_array($shape['items'] ?? null)) {
                $this->applyShapeToSchema($schema->items, $shape['items'], $groups);
            }

            return;
        }

        if ($kind === 'object') {
            $schema->type = 'object';
            $schema->properties = [];
            $required = [];

            foreach ($shape['properties'] ?? [] as $name => $propertyShape) {
                $property = new OA\Property([
                    'property' => (string) $name,
                    '_context' => Util::createWeakContext($schema->_context),
                ]);
                $this->applyShapeToSchema($property, $propertyShape, $groups);
                $schema->properties[] = $property;

                if (($propertyShape['nullable'] ?? false) !== true) {
                    $required[] = (string) $name;
                }
            }

            if ($required !== []) {
                $schema->required = $required;
            }

            return;
        }

        $schema->type = $kind === 'scalar' ? (string) ($shape['type'] ?? 'string') : 'object';
        $schema->nullable = (bool) ($shape['nullable'] ?? false);
    }

    private function addJsonRequestBody(OA\Operation $operation, string $modelClass, ?array $groups = null): void
    {
        $modelRef = $this->modelRegistry->register(new Model(
            LegacyTypeConverter::createType($modelClass),
            groups: $groups
        ));

        /** @var OA\RequestBody $requestBody */
        $requestBody = Util::getChild($operation, OA\RequestBody::class);
        $requestBody->required = true;

        $schema = OpenApiHelper::getJsonRequestBodySchema($requestBody);
        OpenApiHelper::applyModelReference($schema, $modelRef);
    }

    private function addJsonModelResponse(
        OA\Operation $operation,
        int $statusCode,
        string $modelClass,
        string $description,
        ?array $groups = null,
        bool $collection = false
    ): void {
        /** @var OA\Response $response */
        $response = Util::getIndexedCollectionItem($operation, OA\Response::class, (string) $statusCode);
        $response->description = $description;

        $modelRef = $this->modelRegistry->register(new Model(
            LegacyTypeConverter::createType($modelClass),
            groups: $groups
        ));

        $schema = OpenApiHelper::getJsonResponseSchema($response);
        OpenApiHelper::applyModelReference($schema, $modelRef, $collection);
    }

    private function addEmptyResponse(OA\Operation $operation, int $statusCode, string $description): void
    {
        /** @var OA\Response $response */
        $response = Util::getIndexedCollectionItem($operation, OA\Response::class, (string) $statusCode);

        if (Generator::isDefault($response->description)) {
            $response->description = $description;
        }
    }

    /**
     * @return array{0: string|null, 1: list<string>}
     */
    private function extractGroupInfo(MethodCall $call): array
    {
        if (!isset($call->args[1])) {
            return [null, []];
        }

        $value = $call->args[1]->value;

        if (!$value instanceof Expr\ClassConstFetch || !$value->class instanceof Name || 'Groups' !== $value->class->toString()) {
            return [null, []];
        }

        $groupName = $value->name->toString();

        if (!defined(Groups::class.'::'.$groupName)) {
            return [$groupName, []];
        }

        return [$groupName, [constant(Groups::class.'::'.$groupName)->value]];
    }

    /**
     * @param array<string, mixed>|null $shape
     *
     * @return array<string, mixed>|null
     */
    private function applyGroupFallback(?array $shape, ?string $groupName): ?array
    {
        if ($groupName === null) {
            return $shape;
        }

        $guessedClass = $this->guessModelClassFromGroupName($groupName);

        if ($guessedClass === null) {
            return $shape;
        }

        if ($shape === null) {
            return [
                'kind' => 'model',
                'class' => $guessedClass,
                'collection' => str_starts_with($groupName, 'List'),
                'nullable' => false,
            ];
        }

        if (($shape['kind'] ?? null) === 'array' && ($shape['items'] ?? null) === null) {
            return [
                'kind' => 'model',
                'class' => $guessedClass,
                'collection' => true,
                'nullable' => false,
            ];
        }

        return $shape;
    }

    private function guessModelClassFromGroupName(string $groupName): ?string
    {
        $subject = preg_replace('/^(Read|List)/', '', $groupName) ?? $groupName;
        $candidates = [$subject];

        if (str_ends_with($subject, 'ies')) {
            $candidates[] = substr($subject, 0, -3).'y';
        }

        if (str_ends_with($subject, 's')) {
            $candidates[] = substr($subject, 0, -1);
        }

        foreach (array_values(array_unique(array_filter($candidates))) as $candidate) {
            foreach ([
                'App\\Entity\\'.$candidate,
                'App\\Model\\Response\\'.$candidate,
                'App\\Model\\Response\\'.$candidate.'Response',
            ] as $className) {
                if (class_exists($className)) {
                    return $className;
                }
            }
        }

        return null;
    }

    private function extractCallStatusCode(MethodCall $call, int $default): int
    {
        if (!isset($call->args[2])) {
            return $default;
        }

        $statusArg = $call->args[2]->value;

        if ($statusArg instanceof Int_) {
            return $statusArg->value;
        }

        if ($statusArg instanceof Expr\ClassConstFetch && $statusArg->class instanceof Name && 'Response' === $statusArg->class->toString()) {
            return match ($statusArg->name->toString()) {
                'HTTP_NO_CONTENT' => 204,
                'HTTP_OK' => 200,
                default => $default,
            };
        }

        return $default;
    }

    private function extractStatusCode(New_ $newResponse, int $default): int
    {
        if (!isset($newResponse->args[1])) {
            return $default;
        }

        $statusArg = $newResponse->args[1]->value;

        if ($statusArg instanceof Int_) {
            return $statusArg->value;
        }

        if ($statusArg instanceof Expr\ClassConstFetch && $statusArg->class instanceof Name && 'Response' === $statusArg->class->toString()) {
            return match ($statusArg->name->toString()) {
                'HTTP_NO_CONTENT' => 204,
                'HTTP_OK' => 200,
                default => $default,
            };
        }

        return $default;
    }

    private function requiresAuthenticatedUser(ClassMethod $classMethod): bool
    {
        if ($this->hasMethodCall($classMethod, 'denyAccessUnlessGranted') || $this->hasMethodCall($classMethod, 'getUser') || $this->hasMethodCall($classMethod, 'getToken')) {
            return true;
        }

        foreach ($this->findThrowNodes($classMethod) as $throwNode) {
            if ($this->resolveThrownClass($throwNode) === 'UnauthorizedHttpException') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $routePlaceholders
     */
    private function isLookupOnlyEntityArgumentAction(array $routePlaceholders, ReflectionMethod $reflectionMethod, ?ClassMethod $classMethod): bool
    {
        if ($classMethod === null) {
            return false;
        }

        $hasEntityArgument = false;

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $attribute = $parameter->getAttributes('RollandRock\\ParamConverterBundle\\Attribute\\EntityArgument')[0] ?? null;

            if ($attribute === null) {
                continue;
            }

            $hasEntityArgument = true;
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin() || !$this->supportsDoctrineMetadata($type->getName())) {
                return false;
            }

            $attributeInstance = $attribute->newInstance();
            $lookupFields = $attributeInstance->properties ?: $this->entityManager->getClassMetadata($type->getName())->getIdentifierFieldNames();

            foreach ($lookupFields as $lookupField) {
                if (!in_array($lookupField, $routePlaceholders, true)) {
                    return false;
                }
            }

            if ($this->validatesVariable($classMethod, $parameter->getName())) {
                return false;
            }
        }

        return $hasEntityArgument;
    }

    private function supportsDoctrineMetadata(string $class): bool
    {
        try {
            return $this->entityManager->getClassMetadata($class) instanceof ClassMetadata;
        } catch (\Throwable) {
            return false;
        }
    }

    private function validatesVariable(ClassMethod $classMethod, string $variableName): bool
    {
        foreach ((new NodeFinder())->findInstanceOf($classMethod->stmts ?? [], MethodCall::class) as $call) {
            if (!$call instanceof MethodCall || !$this->isNamedMethodCall($call, 'validate')) {
                continue;
            }

            $argument = $call->args[0]->value ?? null;

            if ($argument instanceof Expr\Variable && $argument->name === $variableName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function extractRoutePlaceholders(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    private function guessTag(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        foreach ($segments as $segment) {
            if (!str_starts_with($segment, '{')) {
                return in_array($segment, ['login', 'register', 'reset-password', 'reset-password-request'], true)
                    ? 'auth'
                    : $segment;
            }
        }

        return 'default';
    }

    private function hasMethodCall(ClassMethod $classMethod, string $methodName): bool
    {
        return null !== (new NodeFinder())->findFirst(
            $classMethod->stmts ?? [],
            fn (Node $node): bool => $node instanceof MethodCall && $this->isNamedMethodCall($node, $methodName)
        );
    }

    /**
     * @return Return_[]
     */
    private function findReturnNodes(ClassMethod $classMethod): array
    {
        return (new NodeFinder())->findInstanceOf($classMethod->stmts ?? [], Return_::class);
    }

    /**
     * @return Throw_[]
     */
    private function findThrowNodes(ClassMethod $classMethod): array
    {
        return (new NodeFinder())->findInstanceOf($classMethod->stmts ?? [], Throw_::class);
    }

    private function resolveThrownClass(Throw_ $throwNode): ?string
    {
        if (!$throwNode->expr instanceof New_ || !$throwNode->expr->class instanceof Name) {
            return null;
        }

        return $throwNode->expr->class->getLast();
    }

    private function isNamedMethodCall(MethodCall $call, string $methodName): bool
    {
        return $call->name instanceof Identifier && $call->name->toString() === $methodName;
    }

    private function isNewClass(New_ $newNode, string $shortClassName): bool
    {
        return $newNode->class instanceof Name && $newNode->class->getLast() === $shortClassName;
    }

    private function loadClassMethodNode(ReflectionMethod $reflectionMethod): ?ClassMethod
    {
        $cacheKey = $reflectionMethod->getDeclaringClass()->getName().'::'.$reflectionMethod->getName();

        if (array_key_exists($cacheKey, $this->methodCache)) {
            return $this->methodCache[$cacheKey];
        }

        $fileName = $reflectionMethod->getFileName();

        if (!is_string($fileName) || !is_file($fileName)) {
            return $this->methodCache[$cacheKey] = null;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $statements = $parser->parse(file_get_contents($fileName) ?: '');

        if ($statements === null) {
            return $this->methodCache[$cacheKey] = null;
        }

        $classShortName = $reflectionMethod->getDeclaringClass()->getShortName();
        $methodName = $reflectionMethod->getName();

        foreach ($statements as $statement) {
            if (!$statement instanceof Namespace_) {
                continue;
            }

            foreach ($statement->stmts as $namespaceStatement) {
                if (!$namespaceStatement instanceof Class_ || $namespaceStatement->name?->toString() !== $classShortName) {
                    continue;
                }

                foreach ($namespaceStatement->getMethods() as $method) {
                    if ($method->name->toString() === $methodName) {
                        return $this->methodCache[$cacheKey] = $method;
                    }
                }
            }
        }

        return $this->methodCache[$cacheKey] = null;
    }
}
