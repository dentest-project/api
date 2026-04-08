<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\DomainEntity;
use App\Repository\DomainEntityRepository;
use App\Repository\OrganizationUserRepository;
use App\Repository\ProjectUserRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CreateDomainEntityVoter extends Voter
{
    use WriteProjectVoterTrait;

    public function __construct(
        private readonly DomainEntityRepository $domainEntityRepository,
        ProjectUserRepository $projectUserRepository,
        OrganizationUserRepository $organizationUserRepository
    ) {
        $this->projectUserRepository = $projectUserRepository;
        $this->organizationUserRepository = $organizationUserRepository;
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $attribute === Verb::CREATE && $subject instanceof DomainEntity;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        if (!isset($subject->project)) {
            return false;
        }

        if (null !== $subject->id && null !== $this->domainEntityRepository->find($subject->id)) {
            return false;
        }

        return $this->isAllowedToWriteProject($token, $subject->project);
    }
}
