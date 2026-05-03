<?php

namespace App\Controller;

use App\Entity\ProjectUser;
use App\Manager\ProjectUserManager;
use App\Security\Voter\Verb;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects/{project}/users/{user}/token', requirements: ['project' => '[0-9a-f-]+', 'user' => '[0-9a-f-]+'], methods: ['PUT'])]
class CreateProjectUserToken extends Api
{
    public function __construct(
        private readonly ProjectUserManager $projectUserManager
    ) {}

    public function __invoke(ProjectUser $projectUser): Response
    {
        $this->denyAccessUnlessGranted(Verb::CREATE_TOKEN, $projectUser);

        try {
            $this->projectUserManager->buildToken($projectUser);
        } catch (ORMException | OptimisticLockException $e) {
            throw new UnprocessableEntityHttpException();
        }

        return $this->buildSerializedResponse([
            'token' => $projectUser->token,
        ]);
    }
}
