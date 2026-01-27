<?php

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        return parent::__construct($registry, Organization::class);
    }

    public function getOrganizationsForUser(User $user): iterable
    {
        return $this
            ->createQueryBuilder('o')
            ->join('o.users', 'ou')
            ->join('ou.user', 'u')
            ->where('u.id = :id')
            ->orderBy('o.name', 'ASC')
            ->setParameter('id', $user->id)
            ->getQuery()
            ->getResult();
    }

    public function delete(Organization $organization): void
    {
        $this->_em->remove($organization);
        $this->_em->flush();
    }

    public function save(Organization $organization): void
    {
        $this->_em->persist($organization);
        $this->_em->flush();
    }
}
