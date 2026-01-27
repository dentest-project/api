<?php

declare(strict_types=1);

namespace App\Domain\Fact\Projection\ProjectionBuilder;

use App\Entity\Feature;

final readonly class FeatureProjectionBuilder extends ProjectionBuilder
{
    public function appliesTo(): string
    {
        return Feature::class;
    }
}
