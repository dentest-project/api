<?php

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        return parent::__construct($registry, User::class);
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function delete(User $user): void
    {
        $this->_em->remove($user);
        $this->_em->flush();
    }

    public function search(string $q): iterable
    {
        return $this
            ->createQueryBuilder('u')
            ->where('LOWER(u.username) LIKE LOWER(:q)')
            ->orderBy('u.username', 'ASC')
            ->setParameter('q', sprintf('%%%s%%', $q))
            ->getQuery()
            ->getResult();
    }

    public function searchByOrganization(Organization $organization, string $q): iterable
    {
        return $this
            ->createQueryBuilder('u')
            ->join('u.organizations', 'o')
            ->where('o.organization = :organization')
            ->andWhere('LOWER(u.username) LIKE LOWER(:q)')
            ->orderBy('u.username', 'ASC')
            ->setParameter('organization', $organization)
            ->setParameter('q', sprintf('%%%s%%', $q))
            ->getQuery()
            ->getResult();
    }
}
