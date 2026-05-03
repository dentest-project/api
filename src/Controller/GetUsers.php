<?php

namespace App\Controller;

use App\Model\Request\GetUsersQueryModel;
use App\Repository\OrganizationRepository;
use App\Repository\UserRepository;
use App\Security\Voter\Verb;
use App\Serializer\Groups;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users', methods: ['GET'])]
class GetUsers extends Api
{
    public function __construct(
        private readonly OrganizationRepository $organizationRepository,
        private readonly UserRepository $userRepository
    ) {}

    public function __invoke(GetUsersQueryModel $query): Response
    {
        if (null === $query->q) {
            return new JsonResponse([]);
        }

        if (null !== $query->organization) {
            $organization = $this->organizationRepository->findOneBy(['slug' => $query->organization]);

            if (null === $organization) {
                throw new NotFoundHttpException();
            }

            $this->denyAccessUnlessGranted(Verb::UPDATE, $organization);

            $users = $this->userRepository->searchByOrganization($organization, $query->q);
        } else {
            $users = $this->userRepository->search($query->q);
        }

        return $this->buildSerializedResponse($users, Groups::ListUsers);
    }
}
