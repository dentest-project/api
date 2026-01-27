<?php

declare(strict_types=1);

namespace App\Domain\Fact\Projection\EventProcessor\Feature;

use App\Domain\Fact\Event\Event;
use App\Domain\Fact\Event\FeatureDeleted;
use App\Domain\Fact\Projection\EventProcessor\EventProcessor;
use App\Domain\Fact\Projection\Projection;
use App\Entity\Feature;
use App\Entity\FeatureStatus;

/** @extends EventProcessor<Feature, FeatureDeleted> */
final readonly class FeatureDeletedEventProcessor extends EventProcessor
{
    public function appliesToEvent(): string
    {
        return FeatureDeleted::class;
    }

    public function appliesToProjection(): string
    {
        return Feature::class;
    }

    protected function process(Event $event, Projection $projection): void
    {
        $projection->status = FeatureStatus::Deleted;
    }
}
