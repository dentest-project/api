<?php

namespace App\Controller;

use App\Entity\Organization;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/organizations/{slug}', requirements: ['id' => '[0-9a-z-]+'], methods: ['GET'])]
class GetOrganization extends Api
{
    public function __invoke(Organization $organization): Response
    {
        return $this->buildSerializedResponse($organization, 'READ_ORGANIZATION');
    }
}
