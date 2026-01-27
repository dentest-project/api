<?php

declare(strict_types=1);

namespace App\Domain\Fact\Event\EventItem;

final readonly class NewInlineStepParam
{
    public function __construct(
        public string $id,
        public string $stepPartId,
        public string $defaultValue
    ) {
    }
}
