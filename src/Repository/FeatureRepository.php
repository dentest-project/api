<?php

namespace App\Repository;

use App\Entity\Feature;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FeatureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        return parent::__construct($registry, Feature::class);
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function delete(Feature $feature): void
    {
        $this->_em->remove($feature);
        $this->_em->flush();
    }

    /**
     * @return Feature[]
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function findPullableByRootProject(Project $project): array
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
SELECT f.id 
FROM project p
JOIN path_rec pr ON pr.root_id = p.root_path_id
JOIN feature f ON f.path_id = pr.id
WHERE p.id = :projectId AND f.status <> :draftStatus;
SQL;
        $result = $this->getEntityManager()->getConnection()->fetchAllAssociative($query, [
            'projectId' => $project->id,
            'draftStatus' => Feature::FEATURE_STATUS_DRAFT
        ]);

        $features = $this->findBy(['id' => array_map(fn (array $id): string => $id['id'], $result)]);
        $features = $features instanceof Collection ? $features->toArray() : $features;

        usort($features, fn(Feature $a, Feature $b) => strcmp($a->getDisplayRootPath(), $b->getDisplayRootPath()));

        return $features;
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function save(Feature $feature): void
    {
        $this->_em->persist($feature);
        $this->_em->flush();
    }

    public function updateSummary(string $featureId, string $summary): void
    {
        $this->_em->getConnection()->executeStatement(
            'UPDATE feature SET summary = :summary WHERE id = :id',
            [
                'summary' => $summary,
                'id' => $featureId
            ]
        );
    }

    public function findStatusById(string $featureId): ?string
    {
        $status = $this->_em->getConnection()->fetchOne(
            'SELECT status FROM feature WHERE id = :id',
            ['id' => $featureId]
        );

        return $status !== false ? $status : null;
    }

    /**
     * @return Feature[]
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function findByProjectId(string $projectId): array
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
SELECT f.id
FROM project p
JOIN path_rec pr ON pr.root_id = p.root_path_id
JOIN feature f ON f.path_id = pr.id
WHERE p.id = :projectId
ORDER BY f.title ASC;
SQL;

        $result = $this->getEntityManager()->getConnection()->fetchAllAssociative($query, [
            'projectId' => $projectId
        ]);

        if (count($result) === 0) {
            return [];
        }

        $features = $this->findBy(['id' => array_map(fn (array $row): string => $row['id'], $result)]);
        usort($features, fn (Feature $a, Feature $b) => strcmp($a->title, $b->title));

        return $features;
    }
}
