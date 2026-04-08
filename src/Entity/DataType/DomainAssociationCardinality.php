<?php

declare(strict_types=1);

namespace App\Entity\DataType;

enum DomainAssociationCardinality: string
{
    case EventuallyOne = 'eventually_one';

    case Many = 'many';

    case One = 'one';
}
