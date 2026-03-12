<?php

namespace App\SummaryGeneration\Formatter;

use App\Entity\Feature;
use App\Entity\Scenario;
use Doctrine\Common\Collections\Collection;

class FeatureTextFormatter
{
    public function formatSummarySubject(Feature $feature): string
    {
        $scenarios = $feature->scenarios instanceof Collection ? $feature->scenarios->toArray() : $feature->scenarios;
        $lines = [];

        foreach ($scenarios as $scenario) {
            if (!$scenario instanceof Scenario || $scenario->type === Scenario::TYPE_BACKGROUND) {
                continue;
            }

            $label = match ($scenario->type) {
                Scenario::TYPE_OUTLINE => 'Outline',
                default => 'Scenario'
            };
            $lines[] = $scenario->title !== '' ? sprintf('%s: %s', $label, $scenario->title) : $label;
        }

        if (count($lines) === 0) {
            return '';
        }

        return sprintf(
            "Title: %s\nDescription: %s\nScenario titles:\n%s",
            $feature->title,
            $feature->description !== '' ? $feature->description : '-',
            implode("\n", $lines)
        );
    }

    public function formatContextItem(Feature $feature, bool $includeDisplayPath = false): string
    {
        $label = $includeDisplayPath ? $feature->getDisplayRootPath() : $feature->title;
        $block = sprintf('Item: %s', $label);
        $scenarios = $feature->scenarios instanceof Collection ? $feature->scenarios->toArray() : $feature->scenarios;
        $lines = [];

        foreach ($scenarios as $scenario) {
            if (!$scenario instanceof Scenario || $scenario->type === Scenario::TYPE_BACKGROUND) {
                continue;
            }

            $scenarioLabel = match ($scenario->type) {
                Scenario::TYPE_OUTLINE => 'Outline',
                default => 'Scenario'
            };
            $lines[] = $scenario->title !== '' ? sprintf('%s: %s', $scenarioLabel, $scenario->title) : $scenarioLabel;
        }

        if (count($lines) > 0) {
            $block .= sprintf("\n  - %s", implode("\n  - ", $lines));
        }

        return $block;
    }
}
