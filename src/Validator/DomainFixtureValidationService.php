<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\DataType\DomainAssociationCardinality;
use App\Entity\DataType\DomainPropertyConstraintKind;
use App\Entity\DataType\DomainPropertyStringFormat;
use App\Entity\DataType\DomainPropertyType;
use App\Entity\DomainAssociation;
use App\Entity\DomainEntity;
use App\Entity\DomainFixture;
use App\Entity\DomainFixtureAssociationValue;
use App\Entity\DomainFixturePropertyValue;
use App\Entity\DomainProperty;
use App\Entity\DomainPropertyConstraint;
use App\Helper\UuidHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

readonly class DomainFixtureValidationService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * @return list<DomainFixtureValidationIssue>
     */
    public function validateFixture(DomainFixture $fixture, ?DomainEntity $schemaEntity = null): array
    {
        $issues = [];
        $entity = $schemaEntity ?? ($fixture->entity ?? null);
        $fixtureProjectId = isset($fixture->project) ? UuidHelper::canonicalUuid($fixture->project->id) : null;

        if (!$entity instanceof DomainEntity || null === $entity->id) {
            return [
                new DomainFixtureValidationIssue('entity', 'A fixture must instantiate a persisted domain entity.')
            ];
        }

        if (null === $fixtureProjectId || !isset($entity->project) || $fixtureProjectId !== UuidHelper::canonicalUuid($entity->project->id)) {
            $issues[] = new DomainFixtureValidationIssue('entity', 'A fixture must instantiate an entity from the same project.');
        }

        $propertiesByKey = $this->indexProperties($entity);
        $requiredProperties = [];

        foreach ($entity->properties as $property) {
            if ($property instanceof DomainProperty && !$property->nullable) {
                $requiredProperties[$this->memberKey($property)] = $property;
            }
        }

        $assignedProperties = [];

        foreach ($fixture->propertyValues as $index => $propertyValue) {
            if (!$propertyValue instanceof DomainFixturePropertyValue || !isset($propertyValue->property)) {
                continue;
            }

            $property = $propertyValue->property;
            $propertyKey = $this->memberKey($property);

            if (null === $property->id) {
                $issues[] = new DomainFixtureValidationIssue(
                    sprintf('propertyValues[%d].property', $index),
                    sprintf('The "%s" property must already exist on the entity schema.', $property->name ?? 'unknown')
                );

                continue;
            }

            if (!isset($propertiesByKey[$propertyKey])) {
                $issues[] = new DomainFixtureValidationIssue(
                    sprintf('propertyValues[%d].property', $index),
                    sprintf('The "%s" property is not defined on the instantiated entity.', $property->name ?? 'unknown')
                );

                continue;
            }

            if (isset($assignedProperties[$propertyKey])) {
                $issues[] = new DomainFixtureValidationIssue(
                    sprintf('propertyValues[%d].property', $index),
                    sprintf('The "%s" property can only be assigned once per fixture.', $property->name)
                );

                continue;
            }

            $assignedProperties[$propertyKey] = true;
            unset($requiredProperties[$propertyKey]);

            $issues = [
                ...$issues,
                ...$this->validatePropertyValue($propertyValue, $property, $index),
            ];
        }

        foreach ($requiredProperties as $property) {
            $issues[] = new DomainFixtureValidationIssue(
                'propertyValues',
                sprintf('The "%s" property requires a value.', $property->name)
            );
        }

        $associationsByKey = $this->indexAssociations($entity);
        $associationCounts = [];
        $associationTargetCounts = [];
        $associationTargets = [];

        foreach ($fixture->associationValues as $index => $associationValue) {
            if (
                !$associationValue instanceof DomainFixtureAssociationValue ||
                !isset($associationValue->association, $associationValue->targetFixture)
            ) {
                continue;
            }

            $association = $associationValue->association;
            $associationKey = $this->memberKey($association);

            if (null === $association->id) {
                $issues[] = new DomainFixtureValidationIssue(
                    sprintf('associationValues[%d].association', $index),
                    sprintf('The "%s" association must already exist on the entity schema.', $association->sourceName ?? 'unknown')
                );

                continue;
            }

            if (!isset($associationsByKey[$associationKey])) {
                $issues[] = new DomainFixtureValidationIssue(
                    sprintf('associationValues[%d].association', $index),
                    sprintf('The "%s" association is not defined on the instantiated entity.', $association->sourceName ?? 'unknown')
                );

                continue;
            }

            $targetFixture = $associationValue->targetFixture;

            if (null === $targetFixture->id) {
                $issues[] = new DomainFixtureValidationIssue(
                    sprintf('associationValues[%d].targetFixture', $index),
                    'Fixture associations can only target persisted fixtures.'
                );

                continue;
            }

            if (!isset($targetFixture->entity, $association->targetEntity) || $this->memberKey($targetFixture->entity) !== $this->memberKey($association->targetEntity)) {
                $issues[] = new DomainFixtureValidationIssue(
                    sprintf('associationValues[%d].targetFixture', $index),
                    sprintf('The "%s" association can only target fixtures of "%s".', $association->sourceName, $association->targetEntity->name)
                );

                continue;
            }

            if (
                null === $fixtureProjectId ||
                !isset($targetFixture->project) ||
                UuidHelper::canonicalUuid($targetFixture->project->id) !== $fixtureProjectId
            ) {
                $issues[] = new DomainFixtureValidationIssue(
                    sprintf('associationValues[%d].targetFixture', $index),
                    'Fixture associations can only target fixtures from the same project.'
                );

                continue;
            }

            $targetFixtureKey = $this->memberKey($targetFixture);

            if (isset($associationTargetCounts[$associationKey][$targetFixtureKey])) {
                $issues[] = new DomainFixtureValidationIssue(
                    sprintf('associationValues[%d].targetFixture', $index),
                    sprintf('The "%s" association can only link the same target fixture once.', $association->sourceName)
                );

                continue;
            }

            $associationCounts[$associationKey] = ($associationCounts[$associationKey] ?? 0) + 1;
            $associationTargetCounts[$associationKey][$targetFixtureKey] = ($associationTargetCounts[$associationKey][$targetFixtureKey] ?? 0) + 1;
            $associationTargets[$associationKey][$targetFixtureKey] = $targetFixture;
        }

        foreach ($entity->associations as $association) {
            if (!$association instanceof DomainAssociation) {
                continue;
            }

            $count = $associationCounts[$this->memberKey($association)] ?? 0;
            $cardinalityIssue = $this->validateSourceCardinality($association, $count);

            if (null !== $cardinalityIssue) {
                $issues[] = $cardinalityIssue;
            }
        }

        $reverseCounts = $this->loadReverseCounts($fixture, $associationTargetCounts, $associationTargets);

        foreach ($associationTargetCounts as $associationKey => $targetCounts) {
            $association = $associationsByKey[$associationKey] ?? null;

            if (
                !$association instanceof DomainAssociation ||
                !in_array($association->targetCardinality, [DomainAssociationCardinality::One, DomainAssociationCardinality::EventuallyOne], true)
            ) {
                continue;
            }

            foreach ($targetCounts as $targetFixtureKey => $count) {
                $associationId = $association->id ?? '';
                $targetFixtureId = $reverseCounts['keys'][$associationKey][$targetFixtureKey]['targetFixtureId'] ?? '';
                $existingCount = $reverseCounts['counts'][$associationId][$targetFixtureId] ?? 0;

                if ($existingCount + $count <= 1) {
                    continue;
                }

                $targetFixture = $reverseCounts['keys'][$associationKey][$targetFixtureKey]['targetFixture'] ?? null;
                $issues[] = new DomainFixtureValidationIssue(
                    'associationValues',
                    sprintf(
                        'The "%s" association cannot link more than one source fixture to "%s".',
                        $association->sourceName,
                        $targetFixture instanceof DomainFixture ? $targetFixture->name : $association->targetEntity->name
                    )
                );
            }
        }

        return $issues;
    }

    /**
     * @return array<string, DomainProperty>
     */
    private function indexProperties(DomainEntity $entity): array
    {
        $propertiesByKey = [];

        foreach ($entity->properties as $property) {
            if ($property instanceof DomainProperty) {
                $propertiesByKey[$this->memberKey($property)] = $property;
            }
        }

        return $propertiesByKey;
    }

    /**
     * @return array<string, DomainAssociation>
     */
    private function indexAssociations(DomainEntity $entity): array
    {
        $associationsByKey = [];

        foreach ($entity->associations as $association) {
            if ($association instanceof DomainAssociation) {
                $associationsByKey[$this->memberKey($association)] = $association;
            }
        }

        return $associationsByKey;
    }

    /**
     * @return list<DomainFixtureValidationIssue>
     */
    private function validatePropertyValue(
        DomainFixturePropertyValue $propertyValue,
        DomainProperty $property,
        int $index
    ): array {
        $path = sprintf('propertyValues[%d]', $index);
        $activeSlot = $this->resolveActiveValueSlot($propertyValue);

        if (null === $activeSlot) {
            return [
                new DomainFixtureValidationIssue(
                    $path,
                    sprintf('The "%s" property expects exactly one typed value.', $property->name)
                )
            ];
        }

        $slotIssue = $this->validateValueSlot($property, $activeSlot, $path);

        if (null !== $slotIssue) {
            return [$slotIssue];
        }

        $issues = $this->validateTypedValue($propertyValue, $property, $property->type, $path);

        foreach ($property->constraints as $constraint) {
            if (!$constraint instanceof DomainPropertyConstraint) {
                continue;
            }

            $constraintIssue = $this->validateConstraint($constraint, $propertyValue, $property, $property->type, $path);

            if (null !== $constraintIssue) {
                $issues[] = $constraintIssue;
            }
        }

        return $issues;
    }

    private function validateValueSlot(DomainProperty $property, string $activeSlot, string $path): ?DomainFixtureValidationIssue
    {
        $expectedSlot = match ($property->type) {
            DomainPropertyType::Boolean => 'booleanValue',
            DomainPropertyType::Integer => 'integerValue',
            DomainPropertyType::Decimal => 'decimalValue',
            default => 'stringValue',
        };

        if ($activeSlot === $expectedSlot) {
            return null;
        }

        return new DomainFixtureValidationIssue(
            $path,
            sprintf('The "%s" property expects a %s value.', $property->name, $this->readablePropertyType($property->type))
        );
    }

    /**
     * @return list<DomainFixtureValidationIssue>
     */
    private function validateTypedValue(
        DomainFixturePropertyValue $propertyValue,
        DomainProperty $property,
        DomainPropertyType $type,
        string $path
    ): array {
        return match ($type) {
            DomainPropertyType::Boolean => [],
            DomainPropertyType::Integer => [],
            DomainPropertyType::Decimal => $this->validateDecimalValue($propertyValue, $property, $path),
            DomainPropertyType::Date => $this->isExactDate((string) $propertyValue->stringValue)
                ? []
                : [new DomainFixtureValidationIssue($path, sprintf('The "%s" property must contain a valid date formatted as YYYY-MM-DD.', $property->name))],
            DomainPropertyType::Datetime => false !== strtotime((string) $propertyValue->stringValue)
                ? []
                : [new DomainFixtureValidationIssue($path, sprintf('The "%s" property must contain a valid datetime value.', $property->name))],
            DomainPropertyType::Time => 1 === preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $propertyValue->stringValue)
                ? []
                : [new DomainFixtureValidationIssue($path, sprintf('The "%s" property must contain a valid time value.', $property->name))],
            DomainPropertyType::Uuid => Uuid::isValid((string) $propertyValue->stringValue)
                ? []
                : [new DomainFixtureValidationIssue($path, sprintf('The "%s" property must contain a valid UUID.', $property->name))],
            DomainPropertyType::String, DomainPropertyType::Text => [],
        };
    }

    /**
     * @return list<DomainFixtureValidationIssue>
     */
    private function validateDecimalValue(
        DomainFixturePropertyValue $propertyValue,
        DomainProperty $property,
        string $path
    ): array {
        if (1 === preg_match('/^-?\d+(?:\.\d+)?$/', (string) $propertyValue->decimalValue)) {
            return [];
        }

        return [
            new DomainFixtureValidationIssue(
                $path,
                sprintf('The "%s" property must contain a valid decimal value.', $property->name)
            )
        ];
    }

    private function validateConstraint(
        DomainPropertyConstraint $constraint,
        DomainFixturePropertyValue $propertyValue,
        DomainProperty $property,
        DomainPropertyType $type,
        string $path
    ): ?DomainFixtureValidationIssue {
        return match ($constraint->kind) {
            DomainPropertyConstraintKind::Format => $this->validateFormatConstraint($constraint, $propertyValue, $property, $path),
            DomainPropertyConstraintKind::MinLength => $this->validateMinLengthConstraint($constraint, $propertyValue, $property, $path),
            DomainPropertyConstraintKind::MaxLength => $this->validateMaxLengthConstraint($constraint, $propertyValue, $property, $path),
            DomainPropertyConstraintKind::Pattern => $this->validatePatternConstraint($constraint, $propertyValue, $property, $path),
            DomainPropertyConstraintKind::Min => $this->validateMinConstraint($constraint, $propertyValue, $property, $type, $path),
            DomainPropertyConstraintKind::Max => $this->validateMaxConstraint($constraint, $propertyValue, $property, $type, $path),
            DomainPropertyConstraintKind::Precision => $this->validatePrecisionConstraint($constraint, $propertyValue, $property, $path),
            DomainPropertyConstraintKind::Scale => $this->validateScaleConstraint($constraint, $propertyValue, $property, $path),
        };
    }

    private function validateFormatConstraint(
        DomainPropertyConstraint $constraint,
        DomainFixturePropertyValue $propertyValue,
        DomainProperty $property,
        string $path
    ): ?DomainFixtureValidationIssue {
        if (null === $constraint->format) {
            return null;
        }

        if ($this->matchesStringFormat($constraint->format, (string) $propertyValue->stringValue)) {
            return null;
        }

        return new DomainFixtureValidationIssue(
            $path,
            sprintf('The "%s" property must match the "%s" format.', $property->name, $constraint->format->value)
        );
    }

    private function validateMinLengthConstraint(
        DomainPropertyConstraint $constraint,
        DomainFixturePropertyValue $propertyValue,
        DomainProperty $property,
        string $path
    ): ?DomainFixtureValidationIssue {
        if (null === $constraint->integerValue || $this->stringLength((string) $propertyValue->stringValue) >= $constraint->integerValue) {
            return null;
        }

        return new DomainFixtureValidationIssue(
            $path,
            sprintf('The "%s" property must contain at least %d characters.', $property->name, $constraint->integerValue)
        );
    }

    private function validateMaxLengthConstraint(
        DomainPropertyConstraint $constraint,
        DomainFixturePropertyValue $propertyValue,
        DomainProperty $property,
        string $path
    ): ?DomainFixtureValidationIssue {
        if (null === $constraint->integerValue || $this->stringLength((string) $propertyValue->stringValue) <= $constraint->integerValue) {
            return null;
        }

        return new DomainFixtureValidationIssue(
            $path,
            sprintf('The "%s" property must contain at most %d characters.', $property->name, $constraint->integerValue)
        );
    }

    private function validatePatternConstraint(
        DomainPropertyConstraint $constraint,
        DomainFixturePropertyValue $propertyValue,
        DomainProperty $property,
        string $path
    ): ?DomainFixtureValidationIssue {
        if (null === $constraint->stringValue) {
            return null;
        }

        $match = @preg_match($constraint->stringValue, (string) $propertyValue->stringValue);

        if (1 === $match) {
            return null;
        }

        return new DomainFixtureValidationIssue(
            $path,
            sprintf('The "%s" property must match the configured pattern.', $property->name)
        );
    }

    private function validateMinConstraint(
        DomainPropertyConstraint $constraint,
        DomainFixturePropertyValue $propertyValue,
        DomainProperty $property,
        DomainPropertyType $type,
        string $path
    ): ?DomainFixtureValidationIssue {
        if ($this->compareConstraint($constraint, $propertyValue, $type, false)) {
            return null;
        }

        return new DomainFixtureValidationIssue(
            $path,
            sprintf('The "%s" property is lower than the configured minimum.', $property->name)
        );
    }

    private function validateMaxConstraint(
        DomainPropertyConstraint $constraint,
        DomainFixturePropertyValue $propertyValue,
        DomainProperty $property,
        DomainPropertyType $type,
        string $path
    ): ?DomainFixtureValidationIssue {
        if ($this->compareConstraint($constraint, $propertyValue, $type, true)) {
            return null;
        }

        return new DomainFixtureValidationIssue(
            $path,
            sprintf('The "%s" property is higher than the configured maximum.', $property->name)
        );
    }

    private function validatePrecisionConstraint(
        DomainPropertyConstraint $constraint,
        DomainFixturePropertyValue $propertyValue,
        DomainProperty $property,
        string $path
    ): ?DomainFixtureValidationIssue {
        if (null === $constraint->integerValue || null === $propertyValue->decimalValue || $this->decimalPrecision($propertyValue->decimalValue) <= $constraint->integerValue) {
            return null;
        }

        return new DomainFixtureValidationIssue(
            $path,
            sprintf('The "%s" property must contain at most %d digits.', $property->name, $constraint->integerValue)
        );
    }

    private function validateScaleConstraint(
        DomainPropertyConstraint $constraint,
        DomainFixturePropertyValue $propertyValue,
        DomainProperty $property,
        string $path
    ): ?DomainFixtureValidationIssue {
        if (null === $constraint->integerValue || null === $propertyValue->decimalValue || $this->decimalScale($propertyValue->decimalValue) <= $constraint->integerValue) {
            return null;
        }

        return new DomainFixtureValidationIssue(
            $path,
            sprintf('The "%s" property must contain at most %d decimal places.', $property->name, $constraint->integerValue)
        );
    }

    private function compareConstraint(
        DomainPropertyConstraint $constraint,
        DomainFixturePropertyValue $propertyValue,
        DomainPropertyType $type,
        bool $isMax
    ): bool {
        return match ($type) {
            DomainPropertyType::Integer => null === $constraint->integerValue || null === $propertyValue->integerValue || ($isMax
                ? $propertyValue->integerValue <= $constraint->integerValue
                : $propertyValue->integerValue >= $constraint->integerValue),
            DomainPropertyType::Decimal => null === $constraint->decimalValue || null === $propertyValue->decimalValue || ($isMax
                ? (float) $propertyValue->decimalValue <= (float) $constraint->decimalValue
                : (float) $propertyValue->decimalValue >= (float) $constraint->decimalValue),
            DomainPropertyType::Date,
            DomainPropertyType::Time => null === $constraint->stringValue || null === $propertyValue->stringValue || ($isMax
                ? $propertyValue->stringValue <= $constraint->stringValue
                : $propertyValue->stringValue >= $constraint->stringValue),
            DomainPropertyType::Datetime => null === $constraint->stringValue || null === $propertyValue->stringValue || ($isMax
                ? strtotime($propertyValue->stringValue) <= strtotime($constraint->stringValue)
                : strtotime($propertyValue->stringValue) >= strtotime($constraint->stringValue)),
            default => true,
        };
    }

    private function validateSourceCardinality(DomainAssociation $association, int $count): ?DomainFixtureValidationIssue
    {
        return match ($association->sourceCardinality) {
            DomainAssociationCardinality::One => 1 === $count
                ? null
                : new DomainFixtureValidationIssue(
                    'associationValues',
                    sprintf('The "%s" association requires exactly one target fixture.', $association->sourceName)
                ),
            DomainAssociationCardinality::EventuallyOne => $count <= 1
                ? null
                : new DomainFixtureValidationIssue(
                    'associationValues',
                    sprintf('The "%s" association can target at most one fixture.', $association->sourceName)
                ),
            DomainAssociationCardinality::Many => null,
        };
    }

    /**
     * @param array<string, array<string, int>> $associationTargetCounts
     * @param array<string, array<string, DomainFixture>> $associationTargets
     *
     * @return array{
     *     counts: array<string, array<string, int>>,
     *     keys: array<string, array<string, array{targetFixtureId: string, targetFixture: DomainFixture}>>
     * }
     */
    private function loadReverseCounts(DomainFixture $fixture, array $associationTargetCounts, array $associationTargets): array
    {
        $associationIds = [];
        $targetFixtureIds = [];
        $keys = [];

        foreach ($associationTargets as $associationKey => $targets) {
            foreach ($targets as $targetFixtureKey => $targetFixture) {
                if (
                    !isset($associationTargetCounts[$associationKey][$targetFixtureKey]) ||
                    null === ($targetFixture->id ?? null)
                ) {
                    continue;
                }

                $association = null;

                foreach ($fixture->associationValues as $associationValue) {
                    if (
                        $associationValue instanceof DomainFixtureAssociationValue &&
                        isset($associationValue->association) &&
                        $this->memberKey($associationValue->association) === $associationKey
                    ) {
                        $association = $associationValue->association;
                        break;
                    }
                }

                if (!$association instanceof DomainAssociation || null === $association->id) {
                    continue;
                }

                $associationIds[$association->id] = $association->id;
                $targetFixtureIds[$targetFixture->id] = $targetFixture->id;
                $keys[$associationKey][$targetFixtureKey] = [
                    'targetFixtureId' => $targetFixture->id,
                    'targetFixture' => $targetFixture,
                ];
            }
        }

        if ($associationIds === [] || $targetFixtureIds === []) {
            return [
                'counts' => [],
                'keys' => $keys,
            ];
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(value.association) AS associationId', 'IDENTITY(value.targetFixture) AS targetFixtureId', 'COUNT(value.id) AS total')
            ->from(DomainFixtureAssociationValue::class, 'value')
            ->where('value.association IN (:associationIds)')
            ->andWhere('value.targetFixture IN (:targetFixtureIds)')
            ->groupBy('value.association, value.targetFixture')
            ->setParameter('associationIds', array_values($associationIds))
            ->setParameter('targetFixtureIds', array_values($targetFixtureIds));

        if (null !== $fixture->id) {
            $qb
                ->andWhere('value.fixture != :fixtureId')
                ->setParameter('fixtureId', $fixture->id);
        }

        $counts = [];

        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $counts[$row['associationId']][$row['targetFixtureId']] = (int) $row['total'];
        }

        return [
            'counts' => $counts,
            'keys' => $keys,
        ];
    }

    private function resolveActiveValueSlot(DomainFixturePropertyValue $propertyValue): ?string
    {
        $slots = array_filter(
            [
                'stringValue' => null !== $propertyValue->stringValue,
                'integerValue' => null !== $propertyValue->integerValue,
                'decimalValue' => null !== $propertyValue->decimalValue,
                'booleanValue' => null !== $propertyValue->booleanValue,
            ]
        );

        if (1 !== count($slots)) {
            return null;
        }

        return array_key_first($slots);
    }

    private function readablePropertyType(DomainPropertyType $type): string
    {
        return match ($type) {
            DomainPropertyType::Boolean => 'boolean',
            DomainPropertyType::Date => 'date',
            DomainPropertyType::Datetime => 'datetime',
            DomainPropertyType::Decimal => 'decimal',
            DomainPropertyType::Integer => 'integer',
            DomainPropertyType::String => 'string',
            DomainPropertyType::Text => 'text',
            DomainPropertyType::Time => 'time',
            DomainPropertyType::Uuid => 'UUID',
        };
    }

    private function matchesStringFormat(DomainPropertyStringFormat $format, string $value): bool
    {
        return match ($format) {
            DomainPropertyStringFormat::CountryCode => 1 === preg_match('/^[A-Za-z]{2}$/', $value),
            DomainPropertyStringFormat::Email => false !== filter_var($value, \FILTER_VALIDATE_EMAIL),
            DomainPropertyStringFormat::Ipv4 => false !== filter_var($value, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4),
            DomainPropertyStringFormat::Ipv6 => false !== filter_var($value, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6),
            DomainPropertyStringFormat::Phone => 1 === preg_match('/^\+?[0-9().\-\s]{6,30}$/', $value),
            DomainPropertyStringFormat::Slug => 1 === preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value),
            DomainPropertyStringFormat::Uri,
            DomainPropertyStringFormat::Url => false !== filter_var($value, \FILTER_VALIDATE_URL),
            DomainPropertyStringFormat::Uuid => Uuid::isValid($value),
        };
    }

    private function stringLength(string $value): int
    {
        $length = iconv_strlen($value, 'UTF-8');

        return false === $length ? strlen($value) : $length;
    }

    private function decimalPrecision(string $value): int
    {
        return strlen(str_replace('.', '', ltrim($value, '-')));
    }

    private function decimalScale(string $value): int
    {
        $segments = explode('.', ltrim($value, '-'), 2);

        return isset($segments[1]) ? strlen($segments[1]) : 0;
    }

    private function isExactDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return false !== $date && $date->format('Y-m-d') === $value;
    }

    private function memberKey(object $member): string
    {
        if (property_exists($member, 'id') && is_string($member->id) && '' !== $member->id) {
            return $member->id;
        }

        return sprintf('new:%d', spl_object_id($member));
    }
}
