<?php

declare(strict_types=1);

namespace App\Domain\Fact\Event;

final readonly class ScenarioTitleChanged extends Event
{
    public function __construct(
        EventMetadata $metadata,
        public string $scenarioId,
        public string $newTitle,
    ) {
        parent::__construct($metadata);
    }
}
