<?php

namespace App\Controller;

use App\Entity\ProjectUser;
use App\Repository\ProjectUserRepository;
use App\Security\Voter\Verb;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects/{project}/users/{user}', requirements: ['project' => '[a-f0-9-]+', 'user' => '[a-f0-9-]+'], methods: ['DELETE'])]
class DeleteProjectUser extends Api
{
    public function __construct(
        private readonly ProjectUserRepository $projectUserRepository
    ) {}

    public function __invoke(ProjectUser $projectUser): Response
    {
        $this->denyAccessUnlessGranted(Verb::DELETE, $projectUser);

        try {
            $this->projectUserRepository->delete($projectUser);

            return new JsonResponse();
        } catch (ORMException | OptimisticLockException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }
}
