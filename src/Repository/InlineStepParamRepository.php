<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InlineStepParam;
use App\Entity\StepPart;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InlineStepParamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InlineStepParam::class);
    }

    /**
     * @return string[]
     */
    public function findChoicesForStepPart(StepPart $part): array
    {
        $registeredValues = $this
            ->createQueryBuilder('isp')
            ->select(['DISTINCT isp.content'])
            ->andWhere('isp.stepPart = :part')
            ->setParameter('part', $part)
            ->getQuery()
            ->getArrayResult();
        $registeredValues = array_map(static fn (array $row) => $row['content'], $registeredValues);

        return array_unique(array_merge($part->choices ?? [], $registeredValues));
    }
}
