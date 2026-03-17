<?php

namespace App\SummaryGeneration\SummaryUpdater;

use App\SummaryGeneration\SummaryQueuing\SummaryTarget;
use App\SummaryGeneration\SummaryQueuing\SummaryUpdate;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.summary_updater')]
interface SummaryUpdater
{
    public function supports(SummaryTarget $target): bool;

    public function update(SummaryUpdate $summaryUpdate): void;
}
