<?php

declare(strict_types=1);

namespace App\Entity;

enum StepParamType: string
{
    case None = 'none';

    case Inline = 'inline';

    case Multiline = 'multiline';

    case Table = 'table';
}
