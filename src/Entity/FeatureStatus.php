<?php

declare(strict_types=1);

namespace App\Entity;

enum FeatureStatus: string
{
    case Draft = 'draft';
    case ReadyToDevelop = 'ready_to_dev';
    case Live = 'live';
    case Deleted = 'deleted';
}
