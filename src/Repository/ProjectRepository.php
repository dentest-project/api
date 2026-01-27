<?php

namespace App\Repository;

use App\Entity\Feature;
use App\Entity\Organization;
use App\Entity\Path;
use App\Entity\Project;
use App\Entity\User;
use App\Exception\ProjectNotFoundException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        return parent::__construct($registry, Project::class);
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function delete(Project $project): void
    {
        $this->_em->remove($project);
        $this->_em->flush();
    }

    public function findBySlugs(string $projectSlug, string $organizationSlug = null): ?Project
    {
        $queryBuilder = $this
            ->createQueryBuilder('p')
            ->where('p.slug = :projectSlug')
            ->setParameter('projectSlug', $projectSlug);

        if (null !== $organizationSlug) {
            $queryBuilder
                ->join('p.organization', 'o')
                ->andWhere('o.slug = :organizationSlug')
                ->setParameter('organizationSlug', $organizationSlug);
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    /**
     * @throws ProjectNotFoundException
     * @throws \Doctrine\DBAL\Exception
     */
    public function findFeatureRootProject(Feature $feature): Project
    {
        return $this->find($this->findFeatureRootProjectId($feature->id)['id']);
    }

    /**
     * @throws ProjectNotFoundException
     * @throws \Doctrine\DBAL\Exception
     */
    public function findFeatureRootProjectId(string $featureId): array
    {
        $query = <<<SQL
WITH RECURSIVE path_rec(id, parent_id, root_id) AS (
  SELECT p.id, p.parent_id, p.id AS root_id
  FROM path p
  WHERE p.parent_id IS NULL
UNION ALL
  SELECT p.id, p.parent_id, pr.root_id
  FROM path_rec pr, path p
  WHERE p.parent_id = pr.id
)
SELECT p.id 
FROM project p
JOIN path_rec pr ON pr.root_id = p.root_path_id
JOIN feature f ON f.path_id = pr.id
WHERE f.id = :featureId;
SQL;
        $result = $this->getEntityManager()->getConnection()->fetchAllAssociative($query, [
            'featureId' => $featureId
        ]);

        if (count($result) === 0) {
            throw new ProjectNotFoundException();
        }

        return $result[0];
    }

    public function findOrganizationPublicProjects(Organization $organization): iterable
    {
        return $this
            ->createQueryBuilder('p')
            ->where('p.organization = :organization')
            ->andWhere('p.visibility = :visibility')
            ->orderBy('p.title', 'ASC')
            ->setParameter('organization', $organization)
            ->setParameter('visibility', Project::VISIBILITY_PUBLIC)
            ->getQuery()
            ->getResult();
    }

    public function findOrganizationProjectsForUser(User $user, Organization $organization): iterable
    {
        return $this
            ->createQueryBuilder('p')
            ->leftJoin('p.users', 'ou', Join::WITH, 'ou.user = :user')
            ->where('p.organization = :organization')
            ->andWhere('p.visibility IN (:internalVisibilities) OR (p.visibility = :privateVisibility AND ou.user IS NOT NULL)')
            ->orderBy('p.title', 'ASC')
            ->setParameter('organization', $organization)
            ->setParameter('internalVisibilities', [Project::VISIBILITY_PUBLIC, Project::VISIBILITY_INTERNAL])
            ->setParameter('user', $user)
            ->setParameter('privateVisibility', Project::VISIBILITY_PRIVATE)
            ->getQuery()
            ->getResult();
    }

    public function findProjectsForUser(User $user): iterable
    {
        return $this
            ->createQueryBuilder('p')
            ->join('p.users', 'ou', Join::WITH, 'ou.user = :user')
            ->where('p.organization IS NULL')
            ->setParameter('user', $user)
            ->orderBy('p.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @throws ProjectNotFoundException
     * @throws \Doctrine\DBAL\Exception
     */
    public function findPathRootProject(Path $path): Project
    {
        return $this->find($this->findPathRootProjectId($path->id)['id']);
    }

    /**
     * @throws ProjectNotFoundException
     * @throws \Doctrine\DBAL\Exception
     */
    public function findPathRootProjectId(string $pathId): array
    {
        $query = <<<SQL
WITH RECURSIVE path_rec(id, parent_id, root_id) AS (
  SELECT p.id, p.parent_id, p.id AS root_id
  FROM path p
  WHERE p.parent_id IS NULL
UNION ALL
  SELECT p.id, p.parent_id, pr.root_id
  FROM path_rec pr, path p
  WHERE p.parent_id = pr.id
)
SELECT p.id 
FROM project p
JOIN path_rec pr ON pr.root_id = p.root_path_id
WHERE pr.id = :pathId;
SQL;
        $result = $this->getEntityManager()->getConnection()->fetchAllAssociative($query, [
            'pathId' => $pathId
        ]);

        if (count($result) === 0) {
            throw new ProjectNotFoundException();
        }

        return $result[0];
    }

    public function save(Project $project): void
    {
        $this->_em->persist($project);
        $this->_em->flush();
    }
}
