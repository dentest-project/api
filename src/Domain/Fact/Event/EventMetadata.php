<?php

declare(strict_types=1);

namespace App\Domain\Fact\Event;

use App\Entity\User;
use DateTimeImmutable;

final readonly class EventMetadata
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $happenedAt,
        public string $streamId,
        public ?User $author,
    ) {}
}
