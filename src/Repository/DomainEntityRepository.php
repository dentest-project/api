<?php

namespace App\Repository;

use App\Entity\DomainEntity;
use Doctrine\DBAL\Exception;
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

    /**
     * @throws Exception
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function delete(DomainEntity $domainEntity): void
    {
        $this->_em->transactional(function () use ($domainEntity): void {
            $connection = $this->_em->getConnection();

            $connection->executeStatement(
                <<<SQL
DELETE FROM domain_fixture_association_value
WHERE fixture_id IN (
    SELECT id FROM domain_fixture WHERE entity_id = :entityId
)
OR target_fixture_id IN (
    SELECT id FROM domain_fixture WHERE entity_id = :entityId
)
SQL,
                ['entityId' => $domainEntity->id]
            );

            $connection->executeStatement(
                'DELETE FROM domain_fixture_property_value WHERE fixture_id IN (SELECT id FROM domain_fixture WHERE entity_id = :entityId)',
                ['entityId' => $domainEntity->id]
            );

            $connection->executeStatement(
                'DELETE FROM domain_fixture WHERE entity_id = :entityId',
                ['entityId' => $domainEntity->id]
            );

            $this->_em->remove($domainEntity);
        });
    }
}
