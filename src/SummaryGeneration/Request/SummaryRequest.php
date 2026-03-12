<?php

namespace App\SummaryGeneration\Request;

readonly class SummaryRequest
{
    public function __construct(
        public string $systemPrompt,
        public string $userPrompt
    ) {}
}
