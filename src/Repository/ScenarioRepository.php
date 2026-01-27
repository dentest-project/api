<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Scenario;
use App\Entity\ScenarioType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ScenarioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Scenario::class);
    }

    public function findBackgroundScenariosInProject(Project $project): array
    {
        $featuresQuery = <<<SQL
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
WHERE p.id = :projectId;
SQL;
        $result = $this->getEntityManager()->getConnection()->fetchAllAssociative($featuresQuery, [
            'projectId' => $project->id
        ]);

        return $this->findBy([
            'feature' => array_map(fn (array $id) => $id['id'], $result),
            'type' => ScenarioType::Background->value
        ]);
    }
}
