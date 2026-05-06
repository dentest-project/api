<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Repository\DomainFixtureRepository;
use App\Security\Voter\Verb;
use App\Serializer\Groups;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects/{project}/fixtures', methods: ['GET'])]
class GetProjectFixtures extends Api
{
    public function __construct(
        private readonly DomainFixtureRepository $domainFixtureRepository
    ) {}

    public function __invoke(Project $project): Response
    {
        $this->denyAccessUnlessGranted(Verb::READ, $project);

        return $this->buildSerializedResponse(
            $this->domainFixtureRepository->findByProjectOrdered($project),
            Groups::ReadDomainFixture
        );
    }
}
