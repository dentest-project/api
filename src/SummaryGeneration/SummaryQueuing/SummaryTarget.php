<?php

namespace App\SummaryGeneration\SummaryQueuing;

enum SummaryTarget: string
{
    case Feature = 'feature';

    case Path = 'path';
}
