<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\IssueTracker;
use App\Entity\Organization;
use App\Entity\OrganizationIssueTrackerConfiguration;
use App\Exception\IssueTrackerConfigurationNotFoundException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrganizationIssueTrackerConfigurationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganizationIssueTrackerConfiguration::class);
    }

    /**
     * @throws \App\Exception\IssueTrackerConfigurationNotFoundException
     */
    public function findOneByOrganizationAndTracker(Organization $organization, IssueTracker $issueTracker): OrganizationIssueTrackerConfiguration
    {
        $configuration = $this->findOneBy([
            'organization' => $organization,
            'issueTracker' => $issueTracker
        ]);

        if (!$configuration) {
            throw new IssueTrackerConfigurationNotFoundException();
        }

        return $configuration;
    }

    public function save(OrganizationIssueTrackerConfiguration $configuration): void
    {
        $this->_em->persist($configuration);
        $this->_em->flush();
    }

    public function delete(OrganizationIssueTrackerConfiguration $configuration): void
    {
        $this->_em->remove($configuration);
        $this->_em->flush();
    }
}
