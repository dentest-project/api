<?php

declare(strict_types=1);

namespace App\Entity;

enum ScenarioStepAdverb: string
{
    case Given = 'given';

    case When = 'when';

    case Then = 'then';

    case And = 'and';

    case But = 'but';
}
