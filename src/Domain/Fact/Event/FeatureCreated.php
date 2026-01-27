<?php

declare(strict_types=1);

namespace App\Domain\Fact\Event;

final readonly class FeatureCreated extends Event
{
    public function __construct(
        EventMetadata $metadata,
        public string $pathId,
        public string $title,
        public string $description,
    ) {
        parent::__construct($metadata);
    }
}
