<?php

declare(strict_types=1);

namespace App\Domain\Fact\Event;

final readonly class FeatureDescriptionChanged extends Event
{
    public function __construct(
        EventMetadata $metadata,
        public string $newDescription,
    ) {
        parent::__construct($metadata);
    }
}
