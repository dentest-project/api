<?php

namespace App\Manager;

use App\Entity\OrganizationUser;
use App\Repository\OrganizationUserRepository;

readonly class OrganizationUserManager
{
    public function __construct(
        private OrganizationUserRepository $organizationUserRepository
    ) {}

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function changePermissions(OrganizationUser $organizationUser, array $permissions): void
    {
        $organizationUser->permissions = $permissions;

        $this->organizationUserRepository->save($organizationUser);
    }
}
