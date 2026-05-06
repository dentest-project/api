<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\DomainEntity;
use App\Repository\OrganizationUserRepository;
use App\Repository\ProjectUserRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class DeleteDomainEntityVoter extends Voter
{
    use WriteProjectVoterTrait;

    public function __construct(
        ProjectUserRepository $projectUserRepository,
        OrganizationUserRepository $organizationUserRepository
    ) {
        $this->projectUserRepository = $projectUserRepository;
        $this->organizationUserRepository = $organizationUserRepository;
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $attribute === Verb::DELETE && $subject instanceof DomainEntity;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        if (!isset($subject->project)) {
            return false;
        }

        return $this->isAllowedToWriteProject($token, $subject->project);
    }
}
