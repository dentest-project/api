<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Manager\OrganizationManager;
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

#[Route('/organizations', methods: ['POST'])]
class CreateOrganization extends Api
{
    public function __construct(
        private readonly OrganizationManager $organizationManager
    ) {}

    public function __invoke(#[EntityArgument] Organization $organization): Response
    {
        $this->denyAccessUnlessGranted(Verb::CREATE, $organization);

        $this->validate($organization);

        try {
            $this->organizationManager->createOrganization($organization, $this->getUser());

            return $this->buildSerializedResponse($organization, Groups::ReadOrganization);
        } catch (ORMException | OptimisticLockException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        } catch (UniqueConstraintViolationException $e) {
            throw new ConflictHttpException($e->getMessage());
        }
    }
}
