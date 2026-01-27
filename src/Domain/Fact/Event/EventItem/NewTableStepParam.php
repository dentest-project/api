<?php

declare(strict_types=1);

namespace App\Domain\Fact\Event\EventItem;

final readonly class NewTableStepParam
{
    public function __construct(
        public string $id,
        public string $stepPartId,
        public array $content,
        public bool $headerRow
    ) {
    }
}
