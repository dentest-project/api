<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Manager\OrganizationManager;
use App\Serializer\Groups;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/organizations/{slug}/projects', requirements: ['slug' => '[0-9a-z-]+'], methods: ['GET'])]
class GetOrganizationProjects extends Api
{
    public function __construct(
        private readonly OrganizationManager $organizationManager
    ) {}

    public function __invoke(Organization $organization): Response
    {
        return $this->buildSerializedResponse(
            $this->organizationManager->getOrganizationProjects($organization, $this->getUser()),
            Groups::ListProjects
        );
    }
}
