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

    public function save(Feature $feature): void
    {
        $this->_em->persist($feature);
        $this->_em->flush();
    }
}
