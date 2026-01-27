<?php

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\User;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        return parent::__construct($registry, User::class);
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByEmailForResetPassword(string $email): ?User
    {
        return $this
            ->createQueryBuilder('u')
            ->where('u.email = :email')
            ->andWhere('u.lastResetPasswordRequest < :timestamp OR u.lastResetPasswordRequest IS NULL')
            ->setParameter('email', $email)
            ->setParameter('timestamp', new DateTime('2 hours ago'))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByEmailOrUsername(User $user): ?User
    {
        return $this
            ->createQueryBuilder('u')
            ->where('u.email = :email')
            ->orWhere('u.username = :username')
            ->setParameter('email', $user->email)
            ->setParameter('username', $user->username)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOtherByEmailOrUsername(User $user, string $email, string $username): ?User
    {
        return $this
            ->createQueryBuilder('u')
            ->where('u.email = :email OR u.username = :username')
            ->andWhere('u.id <> :id')
            ->setParameter('email', $email)
            ->setParameter('username', $username)
            ->setParameter('id', $user->id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function delete(User $user): void
    {
        $this->_em->remove($user);
        $this->_em->flush();
    }

    public function save(User $user): void
    {
        $this->_em->persist($user);
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
