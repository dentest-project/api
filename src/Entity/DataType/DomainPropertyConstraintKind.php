<?php

declare(strict_types=1);

namespace App\Entity\DataType;

enum DomainPropertyConstraintKind: string
{
    case Format = 'format';

    case Max = 'max';

    case MaxLength = 'max_length';

    case Min = 'min';

    case MinLength = 'min_length';

    case Pattern = 'pattern';

    case Precision = 'precision';

    case Scale = 'scale';
}
