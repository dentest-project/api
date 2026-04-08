<?php

namespace App\SummaryGeneration\SummaryUpdater;

use App\Entity\Feature;
use App\Repository\FeatureRepository;
use App\Repository\ProjectRepository;
use App\SummaryGeneration\SummaryGenerator\SummaryGenerator;
use App\SummaryGeneration\SummaryQueuing\SummaryTarget;
use App\SummaryGeneration\SummaryQueuing\SummaryUpdate;
use App\SummaryGeneration\SummaryRequest\FeatureSummaryRequestBuilder;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class FeatureSummaryUpdater implements SummaryUpdater
{
    public function __construct(
        private FeatureRepository $featureRepository,
        private ProjectRepository $projectRepository,
        private FeatureSummaryRequestBuilder $featureSummaryRequestBuilder,
        private SummaryGenerator $summaryGenerator,
        private LoggerInterface $summaryLogger
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

        try {
            $this->featureRepository->updateSummary($feature->id, $summary);
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
