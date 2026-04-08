<?php

namespace App\SummaryGeneration\SummaryUpdater;

use App\Entity\Feature;
use App\Entity\Path;
use App\Repository\FeatureRepository;
use App\Repository\PathRepository;
use App\SummaryGeneration\SummaryGenerator\SummaryGenerator;
use App\SummaryGeneration\SummaryQueuing\SummaryTarget;
use App\SummaryGeneration\SummaryQueuing\SummaryUpdate;
use App\SummaryGeneration\SummaryRequest\PathSummaryRequestBuilder;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class PathSummaryUpdater implements SummaryUpdater
{
    public function __construct(
        private PathRepository $pathRepository,
        private FeatureRepository $featureRepository,
        private PathSummaryRequestBuilder $pathSummaryRequestBuilder,
        private SummaryGenerator $summaryGenerator,
        private LoggerInterface $summaryLogger
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

        try {
            $this->pathRepository->updateSummary($path->id, $summary);
        } catch (Throwable $exception) {
            $this->summaryLogger->error('Failed to persist generated summary.', array_merge(
                $summaryRequest->context->toLogContext(),
                [
                    'prompt' => [
                        'system' => $summaryRequest->systemPrompt,
                        'user' => $summaryRequest->userPrompt
                    ],
                    'output' => $summary,
                    'exception' => $exception
                ]
            ));

            throw $exception;
        }
    }
}
