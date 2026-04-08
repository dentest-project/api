<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\DataType\DomainPropertyConstraintKind;
use App\Entity\DataType\DomainPropertyType;
use App\Entity\DomainProperty;
use App\Entity\DomainPropertyConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidDomainPropertyValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidDomainProperty) {
            throw new UnexpectedTypeException($constraint, ValidDomainProperty::class);
        }

        if (!$value instanceof DomainProperty || !isset($value->type)) {
            return;
        }

        $constraintsByKind = [];

        foreach ($value->constraints as $index => $propertyConstraint) {
            if (!$propertyConstraint instanceof DomainPropertyConstraint || !isset($propertyConstraint->kind)) {
                continue;
            }

            $kind = $propertyConstraint->kind;
            $kindKey = $kind->value;

            if (isset($constraintsByKind[$kindKey])) {
                $this->context->buildViolation($constraint->duplicateConstraintMessage)
                    ->atPath(sprintf('constraints[%d].kind', $index))
                    ->addViolation();
                continue;
            }

            $constraintsByKind[$kindKey] = $propertyConstraint;

            match ($kind) {
                DomainPropertyConstraintKind::Format => $this->validateFormatConstraint($propertyConstraint, $value->type, $index, $constraint),
                DomainPropertyConstraintKind::MinLength,
                DomainPropertyConstraintKind::MaxLength,
                DomainPropertyConstraintKind::Precision,
                DomainPropertyConstraintKind::Scale => $this->validateIntegerConstraint($propertyConstraint, $value->type, $index, $constraint),
                DomainPropertyConstraintKind::Pattern => $this->validatePatternConstraint($propertyConstraint, $value->type, $index, $constraint),
                DomainPropertyConstraintKind::Min,
                DomainPropertyConstraintKind::Max => $this->validateBoundaryConstraint($propertyConstraint, $value->type, $index, $constraint),
            };
        }

        $this->validateConstraintConsistency($constraintsByKind, $value->type, $constraint);
    }

    private function validateBoundaryConstraint(
        DomainPropertyConstraint $propertyConstraint,
        DomainPropertyType $propertyType,
        int $index,
        ValidDomainProperty $constraint
    ): void {
        match ($propertyType) {
            DomainPropertyType::Integer => $this->expectIntegerValueOnly($propertyConstraint, $index, $constraint),
            DomainPropertyType::Decimal => $this->expectDecimalValueOnly($propertyConstraint, $index, $constraint),
            DomainPropertyType::Date,
            DomainPropertyType::Datetime,
            DomainPropertyType::Time => $this->expectTemporalValueOnly($propertyConstraint, $propertyType, $index, $constraint),
            default => $this->buildInvalidConstraintViolation($index, $constraint),
        };
    }

    private function validateConstraintConsistency(
        array $constraintsByKind,
        DomainPropertyType $propertyType,
        ValidDomainProperty $constraint
    ): void {
        if (
            isset($constraintsByKind[DomainPropertyConstraintKind::MinLength->value], $constraintsByKind[DomainPropertyConstraintKind::MaxLength->value]) &&
            $constraintsByKind[DomainPropertyConstraintKind::MinLength->value]->integerValue >
            $constraintsByKind[DomainPropertyConstraintKind::MaxLength->value]->integerValue
        ) {
            $this->context->buildViolation($constraint->invalidConsistencyMessage)
                ->atPath('constraints')
                ->addViolation();
        }

        if (isset($constraintsByKind[DomainPropertyConstraintKind::Min->value], $constraintsByKind[DomainPropertyConstraintKind::Max->value])) {
            $isValid = match ($propertyType) {
                DomainPropertyType::Integer => $constraintsByKind[DomainPropertyConstraintKind::Min->value]->integerValue <= $constraintsByKind[DomainPropertyConstraintKind::Max->value]->integerValue,
                DomainPropertyType::Decimal => (float) $constraintsByKind[DomainPropertyConstraintKind::Min->value]->decimalValue <= (float) $constraintsByKind[DomainPropertyConstraintKind::Max->value]->decimalValue,
                DomainPropertyType::Date,
                DomainPropertyType::Time => $constraintsByKind[DomainPropertyConstraintKind::Min->value]->stringValue <= $constraintsByKind[DomainPropertyConstraintKind::Max->value]->stringValue,
                DomainPropertyType::Datetime => strtotime((string) $constraintsByKind[DomainPropertyConstraintKind::Min->value]->stringValue) <= strtotime((string) $constraintsByKind[DomainPropertyConstraintKind::Max->value]->stringValue),
                default => true
            };

            if (!$isValid) {
                $this->context->buildViolation($constraint->invalidConsistencyMessage)
                    ->atPath('constraints')
                    ->addViolation();
            }
        }

        if (
            isset($constraintsByKind[DomainPropertyConstraintKind::Precision->value], $constraintsByKind[DomainPropertyConstraintKind::Scale->value]) &&
            $constraintsByKind[DomainPropertyConstraintKind::Scale->value]->integerValue >
            $constraintsByKind[DomainPropertyConstraintKind::Precision->value]->integerValue
        ) {
            $this->context->buildViolation($constraint->invalidConsistencyMessage)
                ->atPath('constraints')
                ->addViolation();
        }
    }

    private function validateFormatConstraint(
        DomainPropertyConstraint $propertyConstraint,
        DomainPropertyType $propertyType,
        int $index,
        ValidDomainProperty $constraint
    ): void {
        if (!in_array($propertyType, [DomainPropertyType::String, DomainPropertyType::Text], true)) {
            $this->buildInvalidConstraintViolation($index, $constraint);
            return;
        }

        if (
            null === $propertyConstraint->format ||
            null !== $propertyConstraint->stringValue ||
            null !== $propertyConstraint->integerValue ||
            null !== $propertyConstraint->decimalValue
        ) {
            $this->buildInvalidValueViolation(sprintf('constraints[%d]', $index), $constraint);
        }
    }

    private function validateIntegerConstraint(
        DomainPropertyConstraint $propertyConstraint,
        DomainPropertyType $propertyType,
        int $index,
        ValidDomainProperty $constraint
    ): void {
        $allowedTypes = match ($propertyConstraint->kind) {
            DomainPropertyConstraintKind::MinLength,
            DomainPropertyConstraintKind::MaxLength => [DomainPropertyType::String, DomainPropertyType::Text],
            DomainPropertyConstraintKind::Precision,
            DomainPropertyConstraintKind::Scale => [DomainPropertyType::Decimal],
            default => [],
        };

        if (!in_array($propertyType, $allowedTypes, true)) {
            $this->buildInvalidConstraintViolation($index, $constraint);
            return;
        }

        $this->expectIntegerValueOnly($propertyConstraint, $index, $constraint);
    }

    private function validatePatternConstraint(
        DomainPropertyConstraint $propertyConstraint,
        DomainPropertyType $propertyType,
        int $index,
        ValidDomainProperty $constraint
    ): void {
        if (!in_array($propertyType, [DomainPropertyType::String, DomainPropertyType::Text], true)) {
            $this->buildInvalidConstraintViolation($index, $constraint);
            return;
        }

        if (
            null === $propertyConstraint->stringValue ||
            null !== $propertyConstraint->integerValue ||
            null !== $propertyConstraint->decimalValue ||
            null !== $propertyConstraint->format
        ) {
            $this->buildInvalidValueViolation(sprintf('constraints[%d]', $index), $constraint);
        }
    }

    private function expectDecimalValueOnly(
        DomainPropertyConstraint $propertyConstraint,
        int $index,
        ValidDomainProperty $constraint
    ): void {
        if (
            null === $propertyConstraint->decimalValue ||
            null !== $propertyConstraint->stringValue ||
            null !== $propertyConstraint->integerValue ||
            null !== $propertyConstraint->format
        ) {
            $this->buildInvalidValueViolation(sprintf('constraints[%d]', $index), $constraint);
        }
    }

    private function expectIntegerValueOnly(
        DomainPropertyConstraint $propertyConstraint,
        int $index,
        ValidDomainProperty $constraint
    ): void {
        if (
            null === $propertyConstraint->integerValue ||
            null !== $propertyConstraint->stringValue ||
            null !== $propertyConstraint->decimalValue ||
            null !== $propertyConstraint->format
        ) {
            $this->buildInvalidValueViolation(sprintf('constraints[%d]', $index), $constraint);
        }
    }

    private function expectTemporalValueOnly(
        DomainPropertyConstraint $propertyConstraint,
        DomainPropertyType $propertyType,
        int $index,
        ValidDomainProperty $constraint
    ): void {
        if (
            null === $propertyConstraint->stringValue ||
            null !== $propertyConstraint->integerValue ||
            null !== $propertyConstraint->decimalValue ||
            null !== $propertyConstraint->format
        ) {
            $this->buildInvalidValueViolation(sprintf('constraints[%d]', $index), $constraint);
            return;
        }

        $isValid = match ($propertyType) {
            DomainPropertyType::Date => $this->isExactDate($propertyConstraint->stringValue),
            DomainPropertyType::Datetime => false !== strtotime($propertyConstraint->stringValue),
            DomainPropertyType::Time => 1 === preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $propertyConstraint->stringValue),
            default => false
        };

        if (!$isValid) {
            $this->context->buildViolation($constraint->invalidTemporalValueMessage)
                ->setParameter('{{ type }}', $propertyType->value)
                ->atPath(sprintf('constraints[%d].stringValue', $index))
                ->addViolation();
        }
    }

    private function buildInvalidConstraintViolation(int $index, ValidDomainProperty $constraint): void
    {
        $this->context->buildViolation($constraint->invalidConstraintMessage)
            ->atPath(sprintf('constraints[%d].kind', $index))
            ->addViolation();
    }

    private function buildInvalidValueViolation(string $path, ValidDomainProperty $constraint): void
    {
        $this->context->buildViolation($constraint->invalidValueMessage)
            ->atPath($path)
            ->addViolation();
    }

    private function isExactDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return false !== $date && $date->format('Y-m-d') === $value;
    }
}
