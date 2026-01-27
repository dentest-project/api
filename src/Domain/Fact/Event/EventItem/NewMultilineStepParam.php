<?php

declare(strict_types=1);

namespace App\Domain\Fact\Event\EventItem;

final readonly class NewMultilineStepParam
{
    public function __construct(
        public string $id,
        public string $stepPartId
    ) {
    }
}
