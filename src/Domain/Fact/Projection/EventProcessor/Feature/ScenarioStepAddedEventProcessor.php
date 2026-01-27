<?php

declare(strict_types=1);

namespace App\Domain\Fact\Projection\EventProcessor\Feature;

use App\Domain\Fact\Event\Event;
use App\Domain\Fact\Event\ScenarioStepAdded;
use App\Domain\Fact\Projection\EventProcessor\EventProcessor;
use App\Domain\Fact\Projection\Projection;
use App\Entity\Feature;
use App\Entity\Scenario;
use App\Entity\ScenarioStep;
use App\Entity\Step;

/** @extends EventProcessor<Feature, ScenarioStepAdded> */
final readonly class ScenarioStepAddedEventProcessor extends EventProcessor
{
    public function appliesToEvent(): string
    {
        return ScenarioStepAdded::class;
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

        $step = new Step();
        $step->id = $event->stepId;

        $scenarioStep = new ScenarioStep();
        $scenarioStep->id = $event->scenarioStepId;
        $scenarioStep->step = $step;
        $scenarioStep->adverb = $event->adverb;
        $scenarioStep->priority = count($projection->scenarios[$scenarioIndex]->steps);

        /**
         * add params handling
         */

        $projection->scenarios[$scenarioIndex]->steps[] = $scenarioStep;
    }
}
