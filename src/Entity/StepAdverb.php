<?php

declare(strict_types=1);

namespace App\Entity;

enum StepAdverb: string
{
    case Given = 'given';

    case When = 'when';

    case Then = 'then';
}
