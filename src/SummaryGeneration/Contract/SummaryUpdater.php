<?php

namespace App\SummaryGeneration\Contract;

use App\SummaryGeneration\Queue\SummaryTarget;
use App\SummaryGeneration\Queue\SummaryUpdate;

interface SummaryUpdater
{
    public function supports(SummaryTarget $target): bool;

    public function update(SummaryUpdate $summaryUpdate): void;
}
