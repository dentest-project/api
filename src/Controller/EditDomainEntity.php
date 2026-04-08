<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DomainEntity;
use App\Repository\DomainEntityRepository;
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

#[Route('/domain-entities', methods: ['PUT'])]
class EditDomainEntity extends Api
{
    public function __construct(
        private readonly DomainEntityRepository $domainEntityRepository
    ) {}

    public function __invoke(#[EntityArgument] DomainEntity $domainEntity): Response
    {
        $this->denyAccessUnlessGranted(Verb::UPDATE, $domainEntity);

        $this->validate($domainEntity);

        try {
            $this->domainEntityRepository->save($domainEntity);

            return $this->buildSerializedResponse($domainEntity, Groups::ReadDomainModel);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException();
        } catch (ORMException | OptimisticLockException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }
}
