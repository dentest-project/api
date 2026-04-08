<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\DomainEntity;
use App\Helper\UuidHelper;
use App\Repository\DomainEntityRepository;
use App\Repository\OrganizationUserRepository;
use App\Repository\ProjectUserRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class EditDomainEntityVoter extends Voter
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
        return $attribute === Verb::UPDATE && $subject instanceof DomainEntity;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        if (null === $subject->id || !isset($subject->project)) {
            return false;
        }

        $existingDomainEntity = $this->domainEntityRepository->find($subject->id);
        if (!$existingDomainEntity instanceof DomainEntity) {
            return false;
        }

        if (UuidHelper::canonicalUuid($existingDomainEntity->project->id) !== UuidHelper::canonicalUuid($subject->project->id)) {
            return false;
        }

        return $this->isAllowedToWriteProject($token, $subject->project);
    }
}
