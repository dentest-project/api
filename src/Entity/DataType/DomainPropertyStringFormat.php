<?php

declare(strict_types=1);

namespace App\Entity\DataType;

enum DomainPropertyStringFormat: string
{
    case CountryCode = 'country_code';

    case Email = 'email';

    case Ipv4 = 'ipv4';

    case Ipv6 = 'ipv6';

    case Phone = 'phone';

    case Slug = 'slug';

    case Uri = 'uri';

    case Url = 'url';

    case Uuid = 'uuid';
}
