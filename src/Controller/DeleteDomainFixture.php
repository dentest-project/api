<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DomainFixture;
use App\Repository\DomainFixtureRepository;
use App\Security\Voter\Verb;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fixtures/{id}', requirements: ['id' => '[0-9a-f-]+'], methods: ['DELETE'])]
class DeleteDomainFixture extends Api
{
    public function __construct(
        private readonly DomainFixtureRepository $domainFixtureRepository
    ) {}

    public function __invoke(DomainFixture $domainFixture): Response
    {
        $this->denyAccessUnlessGranted(Verb::DELETE, $domainFixture);

        try {
            $this->domainFixtureRepository->delete($domainFixture);

            return new Response();
        } catch (ORMException | OptimisticLockException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }
}
