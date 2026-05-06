<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\DomainFixture;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidDomainFixtureValidator extends ConstraintValidator
{
    public function __construct(
        private DomainFixtureValidationService $validationService
    ) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidDomainFixture) {
            throw new UnexpectedTypeException($constraint, ValidDomainFixture::class);
        }

        if (!$value instanceof DomainFixture) {
            return;
        }

        foreach ($this->validationService->validateFixture($value) as $issue) {
            $this->context->buildViolation($issue->message)
                ->atPath($issue->path)
                ->addViolation();
        }
    }
}
