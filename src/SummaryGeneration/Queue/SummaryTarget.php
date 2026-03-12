<?php

namespace App\SummaryGeneration\Queue;

enum SummaryTarget: string
{
    case Feature = 'feature';

    case Path = 'path';
}
