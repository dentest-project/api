<?php

declare(strict_types=1);

namespace App\FeatureSummary;

use App\Entity\Feature;

interface FeatureSummaryGenerator
{
    public function generate(Feature $feature, array $projectFeatures = []): ?string;
}
