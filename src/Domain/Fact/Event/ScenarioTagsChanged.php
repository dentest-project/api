<?php

declare(strict_types=1);

namespace App\Domain\Fact\Event;

final readonly class ScenarioTagsChanged extends Event
{
    public function __construct(
        EventMetadata $metadata,
        public string $scenarioId,
        public array $newTagIds,
    ) {
        parent::__construct($metadata);
    }
}
