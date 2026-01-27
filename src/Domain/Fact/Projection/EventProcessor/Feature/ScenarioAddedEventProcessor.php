<?php

declare(strict_types=1);

namespace App\Domain\Fact\Projection\EventProcessor\Feature;

use App\Domain\Fact\Event\Event;
use App\Domain\Fact\Event\ScenarioAdded;
use App\Domain\Fact\Projection\EventProcessor\EventProcessor;
use App\Domain\Fact\Projection\Projection;
use App\Entity\Feature;
use App\Entity\Scenario;
use App\Entity\ScenarioType;

/** @extends EventProcessor<Feature, ScenarioAdded> */
final readonly class ScenarioAddedEventProcessor extends EventProcessor
{
    public function appliesToEvent(): string
    {
        return ScenarioAdded::class;
    }

    public function appliesToProjection(): string
    {
        return Feature::class;
    }

    protected function process(Event $event, Projection $projection): void
    {
        $scenario = new Scenario();
        $scenario->id = $event->scenarioId;
        $scenario->title = '';
        $scenario->priority = count($projection->scenarios);
        $scenario->type = ScenarioType::Regular;

        $projection->scenarios[] = $scenario;
    }
}
