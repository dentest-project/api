<?php

namespace App\SummaryGeneration\SummaryQueuing;

use App\Entity\Feature;
use App\Entity\Path;

readonly class SummaryUpdateScheduler
{
    public function __construct(
        private SummaryUpdateQueue $summaryUpdateQueue
    ) {}

    public function scheduleFeatureUpdates(Feature $feature, ?string $previousStatus): void
    {
        if ($feature->id === null) {
            return;
        }

        if ($previousStatus === Feature::FEATURE_STATUS_DRAFT && $feature->status === Feature::FEATURE_STATUS_READY_TO_DEV) {
            $this->summaryUpdateQueue->enqueue(SummaryTarget::Feature, $feature->id);
        }

        $liveMembershipChanged = $previousStatus !== $feature->status
            && ($previousStatus === Feature::FEATURE_STATUS_LIVE || $feature->status === Feature::FEATURE_STATUS_LIVE);

        if ($liveMembershipChanged) {
            $this->enqueuePathAndAncestors($feature->path);
        }
    }

    private function enqueuePathAndAncestors(Path $path): void
    {
        $currentPath = $path;

        while ($currentPath !== null) {
            if ($currentPath->id !== null) {
                $this->summaryUpdateQueue->enqueue(SummaryTarget::Path, $currentPath->id);
            }

            $currentPath = $currentPath->parent;
        }
    }
}
