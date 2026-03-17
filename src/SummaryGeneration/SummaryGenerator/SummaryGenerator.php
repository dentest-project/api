<?php

namespace App\SummaryGeneration\SummaryGenerator;

use App\SummaryGeneration\SummaryRequest\SummaryRequest;

interface SummaryGenerator
{
    public function generate(SummaryRequest $request): ?string;
}
