<?php

namespace App\SummaryGeneration\Request;

use App\Entity\Feature;
use App\Entity\Path;
use App\SummaryGeneration\Formatter\FeatureTextFormatter;
use App\SummaryGeneration\Prompt\SummarySystemPromptBuilder;

readonly class PathSummaryRequestBuilder
{
    public function __construct(
        private FeatureTextFormatter $featureTextFormatter,
        private SummarySystemPromptBuilder $summarySystemPromptBuilder
    ) {}

    /**
     * @param Feature[] $liveFeatures
     */
    public function build(Path $path, array $liveFeatures): ?SummaryRequest
    {
        if (count($liveFeatures) === 0) {
            return null;
        }

        $featuresText = $this->buildLiveFeaturesContext($liveFeatures);
        if ($featuresText === '') {
            return null;
        }

        $liveFeatureCount = count($liveFeatures);
        $minimumSentences = max(1, $liveFeatureCount * 3);
        $maximumSentences = max($minimumSentences, $liveFeatureCount * 7);

        return new SummaryRequest(
            $this->summarySystemPromptBuilder->build($minimumSentences, $maximumSentences),
            sprintf(
<<<PROMPT
Path to summarize:
%s

Live features in this path and its subpaths:
%s
PROMPT,
                $path->getDisplayPath(),
                $featuresText
            )
        );
    }

    /**
     * @param Feature[] $liveFeatures
     */
    private function buildLiveFeaturesContext(array $liveFeatures): string
    {
        $blocks = [];

        foreach ($liveFeatures as $liveFeature) {
            if (!$liveFeature instanceof Feature) {
                continue;
            }

            $block = $this->featureTextFormatter->formatContextItem($liveFeature, true);
            if ($block === '') {
                continue;
            }

            $blocks[] = $block;
        }

        return implode("\n\n", $blocks);
    }
}
