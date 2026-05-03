<?php

namespace App\OpenApi\RouteArgumentDescriber;

use App\Model\Request\QueryRequestModel;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\RouteDescriber\RouteArgumentDescriber\RouteArgumentDescriberInterface;
use Nelmio\ApiDocBundle\RouteDescriber\RouteArgumentDescriber\SymfonyMapQueryStringDescriber;
use Nelmio\ApiDocBundle\Util\LegacyTypeConverter;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class QueryRequestModelDescriber implements RouteArgumentDescriberInterface, ModelRegistryAwareInterface
{
    use ModelRegistryAwareTrait;

    public function describe(ArgumentMetadata $argumentMetadata, OA\Operation $operation): void
    {
        $type = $argumentMetadata->getType();

        if (!is_string($type) || !class_exists($type) || !is_subclass_of($type, QueryRequestModel::class)) {
            return;
        }

        $modelRef = $this->modelRegistry->register(new Model(LegacyTypeConverter::createType($type)));

        if (!isset($operation->_context->{SymfonyMapQueryStringDescriber::CONTEXT_KEY})) {
            $operation->_context->{SymfonyMapQueryStringDescriber::CONTEXT_KEY} = [];
        }

        $operation->_context->{SymfonyMapQueryStringDescriber::CONTEXT_KEY}[] = [
            SymfonyMapQueryStringDescriber::CONTEXT_ARGUMENT_METADATA => $argumentMetadata,
            SymfonyMapQueryStringDescriber::CONTEXT_MODEL_REF => $modelRef,
        ];
    }
}
