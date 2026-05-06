<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DomainFixture;
use App\Security\Voter\Verb;
use App\Serializer\Groups;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fixtures/{id}', requirements: ['id' => '[0-9a-f-]+'], methods: ['GET'])]
class GetDomainFixtureById extends Api
{
    public function __invoke(DomainFixture $domainFixture): Response
    {
        $this->denyAccessUnlessGranted(Verb::READ, $domainFixture);

        return $this->buildSerializedResponse($domainFixture, Groups::ReadDomainFixture);
    }
}
