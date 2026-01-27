<?php

namespace App\Repository;

use App\Entity\Step;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class StepRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        return parent::__construct($registry, Step::class);
    }

    public function save(Step $step): void
    {
        $this->_em->persist($step);
        $this->_em->flush();
    }

    public function delete(Step $step): void
    {
        $this->_em->remove($step);
        $this->_em->flush();
    }
}
