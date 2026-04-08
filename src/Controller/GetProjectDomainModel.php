<?php

namespace App\Controller;

use App\Entity\Project;
use App\Security\Voter\Verb;
use App\Serializer\Groups;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects/{id}/domain-model', requirements: ['id' => '[0-9a-z-]+'], methods: ['GET'])]
class GetProjectDomainModel extends Api
{
    public function __invoke(Project $project): Response
    {
        $this->denyAccessUnlessGranted(Verb::READ, $project);

        return $this->buildSerializedResponse($project->domainEntities, Groups::ReadDomainModel);
    }
}
