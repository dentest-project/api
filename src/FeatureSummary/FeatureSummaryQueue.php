<?php

namespace App\FeatureSummary;

class FeatureSummaryQueue
{
    /** @var array<string, bool> */
    private array $featureIds = [];

    public function enqueue(string $featureId): void
    {
        $this->featureIds[$featureId] = true;
    }

    /**
     * @return string[]
     */
    public function drain(): array
    {
        $ids = array_keys($this->featureIds);
        $this->featureIds = [];

        return $ids;
    }
}
