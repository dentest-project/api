<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidDomainProperty extends Constraint
{
    public string $duplicateConstraintMessage = 'A constraint kind can only be used once on the same property.';

    public string $invalidConstraintMessage = 'This constraint is not allowed for the selected property type.';

    public string $invalidValueMessage = 'This constraint expects a different value payload.';

    public string $invalidTemporalValueMessage = 'This constraint value is not a valid {{ type }}.';

    public string $invalidConsistencyMessage = 'The property constraints are inconsistent.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
