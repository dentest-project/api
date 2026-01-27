<?php

declare(strict_types=1);

namespace App\Domain\Fact\Projection\EventProcessor\Feature;

use App\Domain\Fact\Event\Event;
use App\Domain\Fact\Event\FeatureStatusChanged;
use App\Domain\Fact\Projection\EventProcessor\EventProcessor;
use App\Domain\Fact\Projection\Projection;
use App\Entity\Feature;

/** @extends EventProcessor<Feature, FeatureStatusChanged> */
final readonly class FeatureStatusChangedEventProcessor extends EventProcessor
{
    public function appliesToEvent(): string
    {
        return FeatureStatusChanged::class;
    }

    public function appliesToProjection(): string
    {
        return Feature::class;
    }

    protected function process(Event $event, Projection $projection): void
    {
        $projection->status = $event->newStatus;
    }
}
