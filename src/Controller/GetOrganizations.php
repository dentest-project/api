<?php

namespace App\Controller;

use App\Repository\OrganizationRepository;
use App\Serializer\Groups;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/organizations', methods: ['GET'])]
class GetOrganizations extends Api
{
    public function __construct(
        private readonly OrganizationRepository $organizationRepository
    ) {}

    public function __invoke(): Response
    {
        return $this->buildSerializedResponse(
            $this->organizationRepository->getOrganizationsForUser($this->getUser()),
            Groups::ListOrganizations
        );
    }
}
