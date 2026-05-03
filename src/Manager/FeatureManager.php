<?php

namespace App\Manager;

use App\Entity\Feature;
use App\Entity\Project;
use App\Entity\Scenario;
use App\Model\Response\PullFeatureResponse;
use App\Repository\FeatureRepository;
use App\Repository\ScenarioRepository;
use App\Transformer\FeatureToStringTransformer;

readonly class FeatureManager
{
    public function __construct(
        private FeatureRepository $featureRepository,
        private ScenarioRepository $scenarioRepository,
        private FeatureToStringTransformer $featureToStringTransformer
    ) {}

    /**
     * @throws \Doctrine\DBAL\Exception
     *
     * @return list<PullFeatureResponse>
     */
    public function pull(Project $project, string $inlineParameterWrapper, bool $withId): array
    {
        $features = $this->featureRepository->findPullableByRootProject($project);
        $this->featureToStringTransformer->setInlineParameterWrapper($inlineParameterWrapper);

        return array_map(fn (Feature $feature): array => $this->featureToPulledElement($feature, $withId), $features);
    }

    /**
     * @return Feature[]
     */
    public function findFeaturesWithBackgrounds(Project $project): iterable
    {
        $backgrounds = $this->scenarioRepository->findBackgroundScenariosInProject($project);

        return array_map(fn (Scenario $scenario): Feature => $scenario->feature, $backgrounds);
    }

    private function featureToPulledElement(Feature $feature, bool $withId): array
    {
        $element = [
            'displayPath' => $feature->getDisplayRootPath(),
            'path' => $feature->getRootPath() . '.feature',
            'feature' => $this->featureToStringTransformer->transform($feature)
        ];

        if ($withId) {
            $element['id'] = $feature->id;
        }

        return $element;
    }
}
