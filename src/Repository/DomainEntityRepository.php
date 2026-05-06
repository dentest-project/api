<?php

namespace App\Repository;

use App\Entity\DomainEntity;
use App\Validator\DomainEntityFixtureCompatibilityGuard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DomainEntityRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly DomainEntityFixtureCompatibilityGuard $fixtureCompatibilityGuard
    )
    {
        parent::__construct($registry, DomainEntity::class);
    }

    /**
     * @throws \Doctrine\DBAL\Exception\UniqueConstraintViolationException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function save(DomainEntity $domainEntity): void
    {
        $this->fixtureCompatibilityGuard->assertCompatible($domainEntity);
        $this->_em->persist($domainEntity);
        $this->_em->flush();
    }
}
