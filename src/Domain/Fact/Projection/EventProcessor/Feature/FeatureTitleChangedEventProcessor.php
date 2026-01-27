<?php

declare(strict_types=1);

namespace App\Domain\Fact\Projection\EventProcessor\Feature;

use App\Domain\Fact\Event\Event;
use App\Domain\Fact\Event\FeatureTitleChanged;
use App\Domain\Fact\Projection\EventProcessor\EventProcessor;
use App\Domain\Fact\Projection\Projection;
use App\Entity\Feature;
use Cocur\Slugify\Slugify;

/** @extends EventProcessor<Feature, FeatureTitleChanged> */
final readonly class FeatureTitleChangedEventProcessor extends EventProcessor
{
    public function appliesToEvent(): string
    {
        return FeatureTitleChanged::class;
    }

    public function appliesToProjection(): string
    {
        return Feature::class;
    }

    protected function process(Event $event, Projection $projection): void
    {
        $projection->title = $event->newTitle;
        $projection->slug = Slugify::create()->slugify($event->newTitle);
    }
}
