<?php

namespace App\SummaryGeneration\SummaryQueuing;

class SummaryUpdateQueue
{
    /** @var array<string, SummaryUpdate> */
    private array $updates = [];

    public function enqueue(SummaryTarget $target, string $entityId): void
    {
        $update = new SummaryUpdate($target, $entityId);
        $this->updates[$update->getKey()] = $update;
    }

    /**
     * @return SummaryUpdate[]
     */
    public function drain(): array
    {
        $updates = array_values($this->updates);
        $this->updates = [];

        return $updates;
    }
}
