<?php

declare(strict_types=1);

namespace App\Domain\Fact\Projection\EventProcessor\Feature;

use App\Domain\Fact\Event\Event;
use App\Domain\Fact\Event\ScenarioRemoved;
use App\Domain\Fact\Projection\EventProcessor\EventProcessor;
use App\Domain\Fact\Projection\Projection;
use App\Entity\Feature;
use App\Entity\Scenario;

/** @extends EventProcessor<Feature, ScenarioRemoved> */
final readonly class ScenarioRemovedEventProcessor extends EventProcessor
{
    public function appliesToEvent(): string
    {
        return ScenarioRemoved::class;
    }

    public function appliesToProjection(): string
    {
        return Feature::class;
    }

    protected function process(Event $event, Projection $projection): void
    {
        $scenarioIndex = array_search(
            $event->scenarioId,
            array_map(static fn (Scenario $scenario) => $scenario->id, $projection->scenarios)
        );

        if ($scenarioIndex === false) {
            return;
        }

        unset($projection->scenarios[$scenarioIndex]);
        $projection->scenarios = array_values($projection->scenarios);

        foreach ($projection->scenarios as $index => $scenario) {
            $scenario->priority = $index;
        }
    }
}
