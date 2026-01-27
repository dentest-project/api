<?php

declare(strict_types=1);

namespace App\Domain\Fact\Event;

final readonly class FeatureMoved extends Event
{
    public function __construct(
        EventMetadata $metadata,
        public string $newPathId,
    ) {
        parent::__construct($metadata);
    }
}
