<?php

namespace App\OpenApi\RouteArgumentDescriber;

use App\OpenApi\OpenApiHelper;
use App\OpenApi\RequestSchemaFactory;
use App\OpenApi\RouteDescriber\ControllerConventionDescriber;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\RouteDescriber\RouteArgumentDescriber\RouteArgumentDescriberInterface;
use Nelmio\ApiDocBundle\Util\LegacyTypeConverter;
use OpenApi\Annotations as OA;
use RollandRock\ParamConverterBundle\Attribute\EntityArgument;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class EntityArgumentDescriber implements RouteArgumentDescriberInterface, ModelRegistryAwareInterface
{
    use ModelRegistryAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestSchemaFactory $requestSchemaFactory,
    ) {}

    public function describe(ArgumentMetadata $argumentMetadata, OA\Operation $operation): void
    {
        $attribute = $argumentMetadata->getAttributes(EntityArgument::class)[0] ?? null;

        if (!$attribute instanceof EntityArgument) {
            return;
        }

        if ((bool) ($operation->_context->{ControllerConventionDescriber::CONTEXT_LOOKUP_ONLY_ENTITY_ARGUMENT} ?? false)) {
            return;
        }

        $type = $argumentMetadata->getType();

        if (!is_string($type) || !class_exists($type)) {
            return;
        }

        /** @var OA\RequestBody $requestBody */
        $requestBody = \Nelmio\ApiDocBundle\OpenApiPhp\Util::getChild($operation, OA\RequestBody::class);
        $requestBody->required = !($argumentMetadata->hasDefaultValue() || $argumentMetadata->isNullable());

        $schema = OpenApiHelper::getJsonRequestBodySchema($requestBody);

        if ($this->requestSchemaFactory->supports($type)) {
            $httpMethod = $this->resolveOperationMethod($operation);
            $lookupFields = $attribute->properties;

            if ($lookupFields === [] && $httpMethod !== 'POST') {
                $lookupFields = $this->entityManager->getClassMetadata($type)->getIdentifierFieldNames();
            }

            $this->requestSchemaFactory->describe($schema, $type, [
                'httpMethod' => $httpMethod,
                'lookupFields' => $lookupFields,
                'lookupInPath' => $this->lookupFieldsAreInPath($operation, $lookupFields),
            ]);

            return;
        }

        $modelRef = $this->modelRegistry->register(new Model(LegacyTypeConverter::createType($type)));
        OpenApiHelper::applyModelReference($schema, $modelRef);
    }

    /**
     * @param list<string> $lookupFields
     */
    private function lookupFieldsAreInPath(OA\Operation $operation, array $lookupFields): bool
    {
        $placeholders = $operation->_context->{ControllerConventionDescriber::CONTEXT_ROUTE_PLACEHOLDERS} ?? [];

        if (!is_array($placeholders) || $lookupFields === []) {
            return false;
        }

        foreach ($lookupFields as $lookupField) {
            if (!in_array($lookupField, $placeholders, true)) {
                return false;
            }
        }

        return true;
    }

    private function resolveOperationMethod(OA\Operation $operation): string
    {
        return match (true) {
            $operation instanceof OA\Get => 'GET',
            $operation instanceof OA\Post => 'POST',
            $operation instanceof OA\Put => 'PUT',
            $operation instanceof OA\Patch => 'PATCH',
            $operation instanceof OA\Delete => 'DELETE',
            default => 'ANY',
        };
    }
}
