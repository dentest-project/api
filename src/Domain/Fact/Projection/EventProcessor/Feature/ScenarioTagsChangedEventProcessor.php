<?php

declare(strict_types=1);

namespace App\Domain\Fact\Projection\EventProcessor\Feature;

use App\Domain\Fact\Event\Event;
use App\Domain\Fact\Event\ScenarioTagsChanged;
use App\Domain\Fact\Projection\EventProcessor\EventProcessor;
use App\Domain\Fact\Projection\Projection;
use App\Entity\Feature;
use App\Entity\Scenario;
use App\Entity\Tag;

/** @extends EventProcessor<Feature, ScenarioTagsChanged> */
final readonly class ScenarioTagsChangedEventProcessor extends EventProcessor
{
    public function appliesToEvent(): string
    {
        return ScenarioTagsChanged::class;
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

        $tags = array_map(
            function (string $tagId) {
                $tag = new Tag();

                $tag->id = $tagId;

                return $tag;
            },
            $event->newTagIds
        );

        $projection->scenarios[$scenarioIndex]->tags = $tags;
    }
}
