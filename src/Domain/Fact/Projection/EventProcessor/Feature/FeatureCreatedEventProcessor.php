<?php

declare(strict_types=1);

namespace App\Domain\Fact\Projection\EventProcessor\Feature;

use App\Domain\Fact\Event\Event;
use App\Domain\Fact\Event\FeatureCreated;
use App\Domain\Fact\Projection\EventProcessor\EventProcessor;
use App\Domain\Fact\Projection\Projection;
use App\Entity\Feature;
use App\Entity\FeatureStatus;
use App\Entity\Path;
use Cocur\Slugify\Slugify;

/** @extends EventProcessor<Feature, FeatureCreated> */
final readonly class FeatureCreatedEventProcessor extends EventProcessor
{
    public function appliesToEvent(): string
    {
        return FeatureCreated::class;
    }

    public function appliesToProjection(): string
    {
        return Feature::class;
    }

    protected function process(Event $event, Projection $projection): void
    {
        $path = new Path();
        $path->id = $event->pathId;

        $projection->id = $event->getStreamId();
        $projection->path = $path;
        $projection->title = $event->title;
        $projection->description = $event->description;
        $projection->slug = Slugify::create()->slugify($event->title);
        $projection->status = FeatureStatus::Draft;
        $projection->scenarios = [];
    }
}
