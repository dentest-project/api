<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DomainEntity;
use App\Repository\DomainEntityRepository;
use App\Security\Voter\Verb;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/domain-entities/{id}', requirements: ['id' => '[0-9a-f-]+'], methods: ['DELETE'])]
class DeleteDomainEntity extends Api
{
    public function __construct(
        private readonly DomainEntityRepository $domainEntityRepository
    ) {}

    public function __invoke(DomainEntity $domainEntity): Response
    {
        $this->denyAccessUnlessGranted(Verb::DELETE, $domainEntity);

        try {
            $this->domainEntityRepository->delete($domainEntity);

            return new Response();
        } catch (Exception | ORMException | OptimisticLockException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }
}
