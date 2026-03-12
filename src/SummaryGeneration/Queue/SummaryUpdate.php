<?php

namespace App\SummaryGeneration\Queue;

readonly class SummaryUpdate
{
    public function __construct(
        public SummaryTarget $target,
        public string $entityId
    ) {}

    public function getKey(): string
    {
        return sprintf('%s:%s', $this->target->value, $this->entityId);
    }
}
