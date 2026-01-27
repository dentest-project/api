<?php

declare(strict_types=1);

namespace App\Domain\Fact\Event;

use App\Entity\FeatureStatus;

final readonly class FeatureStatusChanged extends Event
{
    public function __construct(
        EventMetadata $metadata,
        public FeatureStatus $newStatus,
    ) {
        parent::__construct($metadata);
    }
}
