<?php

namespace App\Model\Request;

class PullFeaturesQueryModel extends AbstractQueryRequestModel
{
    public string $inlineParameterWrapper = '';

    public bool $withId = false;
}
