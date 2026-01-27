<?php

declare(strict_types=1);

namespace App\Domain\Fact\Event;

use App\Entity\User;
use DateTimeImmutable;
use function mb_strtoupper;
use function preg_replace;

abstract readonly class Event
{
    public function __construct(protected EventMetadata $metadata) {}

    public function getName(): string
    {
        return mb_strtoupper(
            preg_replace(
                '/(?<!^)[A-Z]/',
                '_$0',
                basename(str_replace('\\', '/', static::class))
            )
        );
    }

    public function getStreamId(): string
    {
        return $this->metadata->streamId;
    }

    public function happenedAt(): DateTimeImmutable
    {
        return $this->metadata->happenedAt;
    }

    public function getAuthor(): ?User
    {
        return $this->metadata->author;
    }
}
