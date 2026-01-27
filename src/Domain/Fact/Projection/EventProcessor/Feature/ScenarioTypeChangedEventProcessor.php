<?php

declare(strict_types=1);

namespace App\Domain\Fact\Projection\EventProcessor\Feature;

use App\Domain\Fact\Event\Event;
use App\Domain\Fact\Event\ScenarioTypeChanged;
use App\Domain\Fact\Projection\EventProcessor\EventProcessor;
use App\Domain\Fact\Projection\Projection;
use App\Entity\Feature;
use App\Entity\Scenario;
use App\Entity\ScenarioType;

/**
 * @extends EventProcessor<Feature, ScenarioTypeChanged>
 */
final readonly class ScenarioTypeChangedEventProcessor extends EventProcessor
{
    public function appliesToEvent(): string
    {
        return ScenarioTypeChanged::class;
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

        if ($event->newType === ScenarioType::Background) {
            $projection->scenarios[$scenarioIndex]->title = '';
            $projection->scenarios[$scenarioIndex]->examples = null;
        }

        $projection->scenarios[$scenarioIndex]->type = $event->newType;
    }
}
