<?php

namespace App\ValueResolver;

use App\Model\Request\RequestModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class RequestModelValueResolver implements ArgumentValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        yield $type::fromRequest($request);
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        $type = $argument->getType();

        if (!is_string($type) || !class_exists($type)) {
            return false;
        }

        return is_subclass_of($type, RequestModel::class);
    }
}
