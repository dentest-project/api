<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DomainFixture;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DomainFixtureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DomainFixture::class);
    }

    /**
     * @throws \Doctrine\DBAL\Exception\UniqueConstraintViolationException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function save(DomainFixture $domainFixture): void
    {
        $this->_em->persist($domainFixture);
        $this->_em->flush();
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function delete(DomainFixture $domainFixture): void
    {
        $this->_em->remove($domainFixture);
        $this->_em->flush();
    }

    /**
     * @return list<DomainFixture>
     */
    public function findByEntityId(string $entityId): array
    {
        return $this->createQueryBuilder('fixture')
            ->addSelect('entity', 'propertyValues', 'property', 'associationValues', 'association', 'targetFixture', 'targetEntity')
            ->innerJoin('fixture.entity', 'entity')
            ->leftJoin('fixture.propertyValues', 'propertyValues')
            ->leftJoin('propertyValues.property', 'property')
            ->leftJoin('fixture.associationValues', 'associationValues')
            ->leftJoin('associationValues.association', 'association')
            ->leftJoin('associationValues.targetFixture', 'targetFixture')
            ->leftJoin('targetFixture.entity', 'targetEntity')
            ->where('entity.id = :entityId')
            ->setParameter('entityId', $entityId)
            ->orderBy('fixture.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<DomainFixture>
     */
    public function findByProjectOrdered(Project $project): array
    {
        return $this->createQueryBuilder('fixture')
            ->addSelect('entity', 'propertyValues', 'property', 'associationValues', 'association', 'associationTargetEntity', 'targetFixture', 'targetFixtureEntity')
            ->innerJoin('fixture.entity', 'entity')
            ->leftJoin('fixture.propertyValues', 'propertyValues')
            ->leftJoin('propertyValues.property', 'property')
            ->leftJoin('fixture.associationValues', 'associationValues')
            ->leftJoin('associationValues.association', 'association')
            ->leftJoin('association.targetEntity', 'associationTargetEntity')
            ->leftJoin('associationValues.targetFixture', 'targetFixture')
            ->leftJoin('targetFixture.entity', 'targetFixtureEntity')
            ->where('fixture.project = :project')
            ->setParameter('project', $project)
            ->orderBy('entity.name', 'ASC')
            ->addOrderBy('fixture.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
