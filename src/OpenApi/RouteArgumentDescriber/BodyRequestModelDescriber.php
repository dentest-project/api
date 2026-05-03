<?php

namespace App\OpenApi\RouteArgumentDescriber;

use App\Model\Request\BodyRequestModel;
use App\OpenApi\OpenApiHelper;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\RouteDescriber\RouteArgumentDescriber\RouteArgumentDescriberInterface;
use Nelmio\ApiDocBundle\Util\LegacyTypeConverter;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class BodyRequestModelDescriber implements RouteArgumentDescriberInterface, ModelRegistryAwareInterface
{
    use ModelRegistryAwareTrait;

    public function describe(ArgumentMetadata $argumentMetadata, OA\Operation $operation): void
    {
        $type = $argumentMetadata->getType();

        if (!is_string($type) || !class_exists($type) || !is_subclass_of($type, BodyRequestModel::class)) {
            return;
        }

        $modelRef = $this->modelRegistry->register(new Model(LegacyTypeConverter::createType($type)));

        /** @var OA\RequestBody $requestBody */
        $requestBody = \Nelmio\ApiDocBundle\OpenApiPhp\Util::getChild($operation, OA\RequestBody::class);
        $requestBody->required = !($argumentMetadata->hasDefaultValue() || $argumentMetadata->isNullable());

        $schema = OpenApiHelper::getJsonRequestBodySchema($requestBody);
        OpenApiHelper::applyModelReference($schema, $modelRef);
    }
}
