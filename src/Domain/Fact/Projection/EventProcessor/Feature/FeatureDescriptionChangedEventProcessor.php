<?php

declare(strict_types=1);

namespace App\Domain\Fact\Projection\EventProcessor\Feature;

use App\Domain\Fact\Event\Event;
use App\Domain\Fact\Event\FeatureDescriptionChanged;
use App\Domain\Fact\Projection\EventProcessor\EventProcessor;
use App\Domain\Fact\Projection\Projection;
use App\Entity\Feature;

/** @extends EventProcessor<Feature, FeatureDescriptionChanged> */
final readonly class FeatureDescriptionChangedEventProcessor extends EventProcessor
{
    public function appliesToEvent(): string
    {
        return FeatureDescriptionChanged::class;
    }

    public function appliesToProjection(): string
    {
        return Feature::class;
    }

    protected function process(Event $event, Projection $projection): void
    {
        $projection->description = $event->newDescription;
    }
}
