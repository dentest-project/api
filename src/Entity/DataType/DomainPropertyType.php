<?php

declare(strict_types=1);

namespace App\Entity\DataType;

enum DomainPropertyType: string
{
    case Boolean = 'boolean';

    case Date = 'date';

    case Datetime = 'datetime';

    case Decimal = 'decimal';

    case Integer = 'integer';

    case String = 'string';

    case Text = 'text';

    case Time = 'time';

    case Uuid = 'uuid';
}
