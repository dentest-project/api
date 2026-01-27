<?php

declare(strict_types=1);

namespace App\Domain\Fact\Event;

interface EventGateway
{
    /**
     * @return iterable<Event>
     */
    public function findOrderedByStreamId(string $streamId): iterable;
}
