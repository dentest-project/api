<?php

declare(strict_types=1);

namespace App\Domain\Fact\Event;

use App\Entity\ScenarioType;

final readonly class ScenarioTypeChanged extends Event
{
    public function __construct(
        EventMetadata $metadata,
        public string $scenarioId,
        public ScenarioType $newType
    ) {
        parent::__construct($metadata);
    }
}
