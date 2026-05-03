<?php

namespace App\Model\Response;

class PullFeatureResponse
{
    public string $displayPath;

    public string $feature;

    public ?string $id = null;

    public string $path;
}
