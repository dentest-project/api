<?php

declare(strict_types=1);

namespace App\Domain\Fact\Projection\EventProcessor\Feature;

use App\Domain\Fact\Event\Event;
use App\Domain\Fact\Event\FeatureTagsChanged;
use App\Domain\Fact\Projection\EventProcessor\EventProcessor;
use App\Domain\Fact\Projection\Projection;
use App\Entity\Feature;
use App\Entity\Tag;

/** @extends EventProcessor<Feature, FeatureTagsChanged> */
final readonly class FeatureTagsChangedEventProcessor extends EventProcessor
{
    public function appliesToEvent(): string
    {
        return FeatureTagsChanged::class;
    }

    public function appliesToProjection(): string
    {
        return Feature::class;
    }

    protected function process(Event $event, Projection $projection): void
    {
        $tags = array_map(
            function (string $tagId) {
                $tag = new Tag();

                $tag->id = $tagId;

                return $tag;
            },
            $event->newTagIds
        );

        $projection->tags = $tags;
    }
}
