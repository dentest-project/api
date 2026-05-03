<?php

namespace App\Model\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class AbstractRequestModel implements BodyRequestModel
{
    public static function fromRequest(Request $request): self
    {
        $requestContent = json_decode($request->getContent(), true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new BadRequestHttpException();
        }

        if (!is_array($requestContent)) {
            throw new BadRequestHttpException();
        }

        /** @var self $model */
        $model = RequestModelHydrator::hydrate(static::class, $requestContent);

        return $model;
    }
}
