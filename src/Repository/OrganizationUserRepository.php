<?php

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\OrganizationUser;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrganizationUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        return parent::__construct($registry, OrganizationUser::class);
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function delete(OrganizationUser $organizationUser): void
    {
        $this->_em->remove($organizationUser);
        $this->_em->flush();
    }

    public function findOneByUserAndOrganization(User $user, Organization $organization): ?OrganizationUser
    {
        return $this->findOneBy(['user' => $user, 'organization' => $organization]);
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function save(OrganizationUser $organizationUser): void
    {
        $this->_em->persist($organizationUser);
        $this->_em->flush();
    }
}
