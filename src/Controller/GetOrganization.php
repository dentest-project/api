<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Serializer\Groups;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/organizations/{slug}', requirements: ['slug' => '[0-9a-z-]+'], methods: ['GET'])]
class GetOrganization extends Api
{
    public function __invoke(Organization $organization): Response
    {
        return $this->buildSerializedResponse($organization, Groups::ReadOrganization);
    }
}
