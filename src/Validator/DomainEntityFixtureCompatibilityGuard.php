<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\DomainEntity;
use App\Exception\IncompatibleDomainSchemaException;
use App\Repository\DomainFixtureRepository;

readonly class DomainEntityFixtureCompatibilityGuard
{
    public function __construct(
        private DomainFixtureRepository $domainFixtureRepository,
        private DomainFixtureValidationService $fixtureValidationService
    ) {}

    public function assertCompatible(DomainEntity $domainEntity): void
    {
        if (null === $domainEntity->id) {
            return;
        }

        foreach ($this->domainFixtureRepository->findByEntityId($domainEntity->id) as $fixture) {
            $issues = $this->fixtureValidationService->validateFixture($fixture, $domainEntity);

            if ($issues !== []) {
                throw IncompatibleDomainSchemaException::forFixture($fixture, $issues[0]);
            }
        }
    }
}
