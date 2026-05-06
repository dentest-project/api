<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DomainFixture;
use App\Entity\Project;
use App\Repository\DomainFixtureRepository;
use App\Security\Voter\Verb;
use App\Serializer\Groups;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use RollandRock\ParamConverterBundle\Attribute\EntityArgument;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects/{domainFixtureProject}/fixtures', methods: ['POST'])]
class CreateDomainFixture extends Api
{
    public function __construct(
        private readonly DomainFixtureRepository $domainFixtureRepository
    ) {}

    public function __invoke(Project $domainFixtureProject, #[EntityArgument] DomainFixture $domainFixture): Response
    {
        $domainFixture->project = $domainFixtureProject;

        $this->denyAccessUnlessGranted(Verb::CREATE, $domainFixture);

        $this->validate($domainFixture);

        try {
            $this->domainFixtureRepository->save($domainFixture);

            return $this->buildSerializedResponse($domainFixture, Groups::ReadDomainFixture);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException();
        } catch (ORMException | OptimisticLockException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }
}
