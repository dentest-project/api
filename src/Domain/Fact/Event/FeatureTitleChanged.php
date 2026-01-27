<?php

declare(strict_types=1);

namespace App\Domain\Fact\Event;

final readonly class FeatureTitleChanged extends Event
{
    public function __construct(
        EventMetadata $metadata,
        public string $newTitle,
    ) {
        parent::__construct($metadata);
    }
}
