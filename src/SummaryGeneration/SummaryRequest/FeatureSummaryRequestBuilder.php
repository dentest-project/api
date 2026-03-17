<?php

namespace App\SummaryGeneration\SummaryRequest;

use App\Entity\Feature;
use App\Entity\Path;
use App\SummaryGeneration\SummaryFormatter\FeatureTextFormatter;
use App\SummaryGeneration\SummaryPromptBuilder\SummarySystemPromptBuilder;

readonly class FeatureSummaryRequestBuilder
{
    public function __construct(
        private FeatureTextFormatter $featureTextFormatter,
        private SummarySystemPromptBuilder $summarySystemPromptBuilder
    ) {}

    /**
     * @param Feature[] $projectFeatures
     */
    public function build(Feature $feature, array $projectFeatures): ?SummaryRequest
    {
        $featureText = $this->featureTextFormatter->formatSummarySubject($feature);
        if ($featureText === '') {
            return null;
        }

        $contextText = $this->buildProjectContext($projectFeatures, $feature);

        return new SummaryRequest(
            $this->summarySystemPromptBuilder->build(3, 7),
            $contextText !== ''
                ? sprintf(
<<<PROMPT
You are given all project items as context. Use them only to disambiguate the edited feature.

Project items (excluding the edited one), with their scenario titles:
%s

Edited feature:
%s
PROMPT,
                    $contextText,
                    $featureText
                )
                : sprintf(
<<<PROMPT
Edited feature:
%s
PROMPT,
                    $featureText
                )
        );
    }

    /**
     * @param Feature[] $projectFeatures
     */
    private function buildProjectContext(array $projectFeatures, Feature $editedFeature): string
    {
        $superPath = $editedFeature->path->parent ?? $editedFeature->path;
        $blocks = [];

        foreach ($projectFeatures as $projectFeature) {
            if (!$projectFeature instanceof Feature) {
                continue;
            }

            if ($projectFeature->id === $editedFeature->id) {
                continue;
            }

            if (!$this->isUnderPath($projectFeature->path ?? null, $superPath)) {
                continue;
            }

            $block = $this->featureTextFormatter->formatContextItem($projectFeature);
            if ($block === '') {
                continue;
            }

            $blocks[] = $block;
        }

        return implode("\n\n", $blocks);
    }

    private function isUnderPath(?Path $path, Path $ancestor): bool
    {
        $currentPath = $path;

        while ($currentPath !== null) {
            if ($currentPath->id === $ancestor->id) {
                return true;
            }

            $currentPath = $currentPath->parent;
        }

        return false;
    }
}
