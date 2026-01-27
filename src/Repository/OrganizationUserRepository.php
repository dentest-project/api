<?php

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\OrganizationUser;
use App\Entity\User;
use App\Security\OrganizationPermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrganizationUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        return parent::__construct($registry, OrganizationUser::class);
    }

    public function delete(OrganizationUser $organizationUser): void
    {
        $this->_em->remove($organizationUser);
        $this->_em->flush();
    }

    public function findOneByUserAndOrganization(User $user, Organization $organization): ?OrganizationUser
    {
        return $this->findOneBy(['user' => $user, 'organization' => $organization]);
    }

    public function makeAdmin(User $user, Organization $organization): void
    {
        $organizationUser = new OrganizationUser();
        $organizationUser->organization = $organization;
        $organizationUser->user = $user;
        $organizationUser->permissions = [OrganizationPermission::ADMIN];

        $this->save($organizationUser);
    }

    public function save(OrganizationUser $organizationUser): void
    {
        $this->_em->persist($organizationUser);
        $this->_em->flush();
    }
}
