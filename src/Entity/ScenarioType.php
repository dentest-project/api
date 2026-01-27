<?php

declare(strict_types=1);

namespace App\Entity;

enum ScenarioType: string
{
    case Regular = 'regular';
    case Background = 'background';
    case Outline = 'outline';
}
