<?php

namespace App\SummaryGeneration\Updater;

use App\Entity\Feature;
use App\Repository\FeatureRepository;
use App\Repository\ProjectRepository;
use App\SummaryGeneration\Contract\SummaryGenerator;
use App\SummaryGeneration\Contract\SummaryUpdater;
use App\SummaryGeneration\Queue\SummaryTarget;
use App\SummaryGeneration\Queue\SummaryUpdate;
use App\SummaryGeneration\Request\FeatureSummaryRequestBuilder;
use Throwable;

readonly class FeatureSummaryUpdater implements SummaryUpdater
{
    public function __construct(
        private FeatureRepository $featureRepository,
        private ProjectRepository $projectRepository,
        private FeatureSummaryRequestBuilder $featureSummaryRequestBuilder,
        private SummaryGenerator $summaryGenerator
    ) {}

    public function supports(SummaryTarget $target): bool
    {
        return $target === SummaryTarget::Feature;
    }

    public function update(SummaryUpdate $summaryUpdate): void
    {
        $feature = $this->featureRepository->find($summaryUpdate->entityId);

        if (!$feature instanceof Feature || $feature->id === null) {
            return;
        }

        $projectFeatures = [];

        try {
            $projectId = $this->projectRepository->findFeatureRootProjectId($feature->id)['id'] ?? null;
            if ($projectId !== null) {
                $projectFeatures = $this->featureRepository->findByProjectId($projectId);
            }
        } catch (Throwable) {
            $projectFeatures = [];
        }

        $summaryRequest = $this->featureSummaryRequestBuilder->build($feature, $projectFeatures);
        if ($summaryRequest === null) {
            return;
        }

        $summary = $this->summaryGenerator->generate($summaryRequest);

        if (!$summary || $summary === $feature->summary) {
            return;
        }

        $this->featureRepository->updateSummary($feature->id, $summary);
    }
}
