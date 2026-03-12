<?php

namespace App\SummaryGeneration\Contract;

use App\SummaryGeneration\Request\SummaryRequest;

interface SummaryGenerator
{
    public function generate(SummaryRequest $request): ?string;
}
