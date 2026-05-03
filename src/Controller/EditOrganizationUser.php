<?php

namespace App\Controller;

use App\Entity\OrganizationUser;
use App\Manager\OrganizationUserManager;
use App\Model\Request\UpdateOrganizationUserRequestModel;
use App\Security\Voter\Verb;
use App\Serializer\Groups;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/organizations/{organization}/users/{user}', requirements: ['organization' => '[a-f0-9-]+', 'user' => '[a-f0-9-]+'], methods: ['PUT'])]
class EditOrganizationUser extends Api
{
    public function __construct(
        private readonly OrganizationUserManager $organizationUserManager
    ) {}

    public function __invoke(OrganizationUser $organizationUser, UpdateOrganizationUserRequestModel $model): Response
    {
        $this->denyAccessUnlessGranted(Verb::UPDATE, $organizationUser);
        $this->validate($model);

        try {
            $this->organizationUserManager->changePermissions($organizationUser, $model->permissions);

            return $this->buildSerializedResponse($organizationUser, Groups::ReadOrganizationUser);
        } catch (ORMException | OptimisticLockException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }
}
