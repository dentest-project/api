<?php

namespace App\FeatureSummary;

use App\Repository\FeatureRepository;
use App\Repository\ProjectRepository;
use Throwable;

readonly class FeatureSummaryUpdater
{
    public function __construct(
        private FeatureRepository $featureRepository,
        private ProjectRepository $projectRepository,
        private FeatureSummaryGenerator $featureSummaryGenerator
    ) {}

    public function update(string $featureId): void
    {
        $feature = $this->featureRepository->find($featureId);

        if ($feature === null) {
            return;
        }

        $projectFeatures = [];

        try {
            $projectId = $this->projectRepository->findFeatureRootProjectId($featureId)['id'] ?? null;
            if ($projectId) {
                $projectFeatures = $this->featureRepository->findByProjectId($projectId);
            }
        } catch (Throwable) {
            $projectFeatures = [];
        }

        $summary = $this->featureSummaryGenerator->generate($feature, $projectFeatures);

        if (!$summary || $summary === $feature->summary) {
            return;
        }

        $this->featureRepository->updateSummary($featureId, $summary);
    }
}
