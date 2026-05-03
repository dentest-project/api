<?php

namespace App\Controller;

use App\Entity\OrganizationUser;
use App\Repository\OrganizationUserRepository;
use App\Security\Voter\Verb;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/organizations/{organization}/users/{user}', requirements: ['organization' => '[a-f0-9-]+', 'user' => '[a-f0-9-]+'], methods: ['DELETE'])]
class DeleteOrganizationUser extends Api
{
    public function __construct(
        private readonly OrganizationUserRepository $organizationUserRepository
    ) {}

    public function __invoke(OrganizationUser $organizationUser): Response
    {
        $this->denyAccessUnlessGranted(Verb::DELETE, $organizationUser);

        try {
            $this->organizationUserRepository->delete($organizationUser);

            return new JsonResponse();
        } catch (ORMException | OptimisticLockException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }
}
