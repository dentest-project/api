<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\StepRepository;
use App\Security\Voter\Verb;
use App\Serializer\Groups;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects/{id}/steps', requirements: ['id' => '[0-9a-f-]+'], methods: ['GET'])]
class GetProjectSteps extends Api
{
    public function __construct(
        private readonly StepRepository $stepRepository
    ) {}

    public function __invoke(Project $project): Response
    {
        $this->denyAccessUnlessGranted(Verb::WRITE_IN, $project);

        return $this->buildSerializedResponse(
            $this->stepRepository->findBy(['project' => $project]),
            Groups::ReadStep
        );
    }
}
