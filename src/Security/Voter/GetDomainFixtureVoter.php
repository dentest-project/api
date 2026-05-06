<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\DomainFixture;
use App\Repository\OrganizationUserRepository;
use App\Repository\ProjectUserRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class GetDomainFixtureVoter extends Voter
{
    use ReadProjectVoterTrait;

    public function __construct(
        OrganizationUserRepository $organizationUserRepository,
        ProjectUserRepository $projectUserRepository
    ) {
        $this->organizationUserRepository = $organizationUserRepository;
        $this->projectUserRepository = $projectUserRepository;
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $attribute === Verb::READ && $subject instanceof DomainFixture;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        if (!isset($subject->project)) {
            return false;
        }

        return $this->isAllowedToReadProject($token, $subject->project);
    }
}
