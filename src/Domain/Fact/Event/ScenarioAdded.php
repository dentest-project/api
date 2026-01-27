<?php

declare(strict_types=1);

namespace App\Domain\Fact\Event;

final readonly class ScenarioAdded extends Event
{
    public function __construct(
        EventMetadata $metadata,
        public string $scenarioId
    ) {
        parent::__construct($metadata);
    }
}
