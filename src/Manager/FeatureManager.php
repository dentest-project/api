<?php

namespace App\Manager;

use App\Entity\Feature;
use App\Entity\Project;
use App\Repository\FeatureRepository;
use App\Transformer\FeatureToStringTransformer;
use Doctrine\Common\Collections\Collection;

readonly class FeatureManager
{
    public function __construct(
        private FeatureRepository $featureRepository,
        private FeatureToStringTransformer $featureToStringTransformer
    ) {}

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function pull(Project $project, string $inlineParameterWrapper)
    {
        $features = $this->featureRepository->findPullableByRootProject($project);
        $this->featureToStringTransformer->setInlineParameterWrapper($inlineParameterWrapper);

        return array_map(fn (Feature $feature): array => $this->featureToPulledElement($feature, $inlineParameterWrapper), $features);
    }

    private function featureToPulledElement(Feature $feature): array
    {
        return [
            'displayPath' => $feature->getDisplayRootPath(),
            'path' => $feature->getRootPath() . '.feature',
            'feature' => $this->featureToStringTransformer->transform($feature)
        ];
    }
}
