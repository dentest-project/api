<?php

namespace App\Model\Request;

use Symfony\Component\HttpFoundation\Request;

abstract class AbstractQueryRequestModel implements QueryRequestModel
{
    public static function fromRequest(Request $request): self
    {
        /** @var self $model */
        $model = RequestModelHydrator::hydrate(static::class, $request->query->all());

        return $model;
    }
}
