<?php

declare(strict_types=1);

namespace App\Domain\Fact\Projection\EventProcessor\Feature;

use App\Domain\Fact\Event\Event;
use App\Domain\Fact\Event\FeatureMoved;
use App\Domain\Fact\Projection\EventProcessor\EventProcessor;
use App\Domain\Fact\Projection\Projection;
use App\Entity\Feature;

/** @extends EventProcessor<Feature, FeatureMoved> */
final readonly class FeatureMovedEventProcessor extends EventProcessor
{
    public function appliesToEvent(): string
    {
        return FeatureMoved::class;
    }

    public function appliesToProjection(): string
    {
        return Feature::class;
    }

    protected function process(Event $event, Projection $projection): void
    {
        $projection->path->id = $event->newPathId;
    }
}
