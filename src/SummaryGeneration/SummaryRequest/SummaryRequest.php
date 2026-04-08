<?php

namespace App\SummaryGeneration\SummaryRequest;

readonly class SummaryRequest
{
    public function __construct(
        public string $systemPrompt,
        public string $userPrompt,
        public SummaryRequestContext $context
    ) {}
}
