<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\DomainFixture;
use App\Helper\UuidHelper;
use App\Repository\DomainFixtureRepository;
use App\Repository\OrganizationUserRepository;
use App\Repository\ProjectUserRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class EditDomainFixtureVoter extends Voter
{
    use WriteProjectVoterTrait;

    public function __construct(
        private readonly DomainFixtureRepository $domainFixtureRepository,
        ProjectUserRepository $projectUserRepository,
        OrganizationUserRepository $organizationUserRepository
    ) {
        $this->projectUserRepository = $projectUserRepository;
        $this->organizationUserRepository = $organizationUserRepository;
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $attribute === Verb::UPDATE && $subject instanceof DomainFixture;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        if (null === $subject->id || !isset($subject->project)) {
            return false;
        }

        $existingFixture = $this->domainFixtureRepository->find($subject->id);

        if (!$existingFixture instanceof DomainFixture) {
            return false;
        }

        if (UuidHelper::canonicalUuid($existingFixture->project->id) !== UuidHelper::canonicalUuid($subject->project->id)) {
            return false;
        }

        return $this->isAllowedToWriteProject($token, $subject->project);
    }
}
