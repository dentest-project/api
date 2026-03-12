<?php

namespace App\SummaryGeneration\Updater;

use App\Entity\Feature;
use App\Entity\Path;
use App\Repository\FeatureRepository;
use App\Repository\PathRepository;
use App\SummaryGeneration\Contract\SummaryGenerator;
use App\SummaryGeneration\Contract\SummaryUpdater;
use App\SummaryGeneration\Queue\SummaryTarget;
use App\SummaryGeneration\Queue\SummaryUpdate;
use App\SummaryGeneration\Request\PathSummaryRequestBuilder;

readonly class PathSummaryUpdater implements SummaryUpdater
{
    public function __construct(
        private PathRepository $pathRepository,
        private FeatureRepository $featureRepository,
        private PathSummaryRequestBuilder $pathSummaryRequestBuilder,
        private SummaryGenerator $summaryGenerator
    ) {}

    public function supports(SummaryTarget $target): bool
    {
        return $target === SummaryTarget::Path;
    }

    public function update(SummaryUpdate $summaryUpdate): void
    {
        $path = $this->pathRepository->find($summaryUpdate->entityId);

        if (!$path instanceof Path || $path->id === null) {
            return;
        }

        $liveFeatures = $this->featureRepository->findByPathIdAndDescendants($path->id, Feature::FEATURE_STATUS_LIVE);

        if (count($liveFeatures) === 0) {
            if ($path->summary !== '') {
                $this->pathRepository->updateSummary($path->id, '');
            }

            return;
        }

        $summaryRequest = $this->pathSummaryRequestBuilder->build($path, $liveFeatures);
        if ($summaryRequest === null) {
            return;
        }

        $summary = $this->summaryGenerator->generate($summaryRequest);

        if (!$summary || $summary === $path->summary) {
            return;
        }

        $this->pathRepository->updateSummary($path->id, $summary);
    }
}
