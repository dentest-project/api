<?php

namespace App\Controller;

use App\Entity\ProjectUser;
use App\Manager\ProjectUserManager;
use App\Model\Request\UpdateProjectUserRequestModel;
use App\Security\Voter\Verb;
use App\Serializer\Groups;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects/{project}/users/{user}', requirements: ['project' => '[a-f0-9-]+', 'user' => '[a-f0-9-]+'], methods: ['PUT'])]
class EditProjectUser extends Api
{
    public function __construct(
        private readonly ProjectUserManager $projectUserManager
    ) {}

    public function __invoke(ProjectUser $projectUser, UpdateProjectUserRequestModel $model): Response
    {
        $this->denyAccessUnlessGranted(Verb::UPDATE, $projectUser);
        $this->validate($model);

        try {
            $this->projectUserManager->changePermissions($projectUser, $model->permissions);

            return $this->buildSerializedResponse($projectUser, Groups::ReadProjectUser);
        } catch (ORMException | OptimisticLockException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }
}
