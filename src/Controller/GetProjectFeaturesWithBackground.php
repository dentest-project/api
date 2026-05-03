<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Manager\FeatureManager;
use App\Security\Voter\Verb;
use App\Serializer\Groups;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects/{id}/features-with-backgrounds', requirements: ['id' => '[0-9a-f-]+'], methods: ['GET'])]
class GetProjectFeaturesWithBackground extends Api
{
    public function __construct(
        private readonly FeatureManager $featureManager
    ) {}

    public function __invoke(Project $project): Response
    {
        $this->denyAccessUnlessGranted(Verb::WRITE_IN, $project);

        return $this->buildSerializedResponse(
            $this->featureManager->findFeaturesWithBackgrounds($project),
            Groups::ListFeatures
        );
     }
}
