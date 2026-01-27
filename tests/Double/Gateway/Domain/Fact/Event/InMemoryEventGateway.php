<?php

declare(strict_types=1);

namespace App\Tests\Double\Gateway\Domain\Fact\Event;

use App\Domain\Fact\Event\Event;
use App\Domain\Fact\Event\EventGateway;

class InMemoryEventGateway implements EventGateway
{
    /**
     * @var iterable<\App\Domain\Fact\Event\Event>
     */
    public static iterable $events = [];

    public function findOrderedByStreamId(string $streamId): iterable
    {
        $concernedEvents = array_filter(self::$events, static fn (Event $event) => $event->getStreamId() === $streamId);

        usort($concernedEvents, static fn (Event $event1, Event $event2) => $event1->happenedAt() <=> $event2->happenedAt());

        return $concernedEvents;
    }
}
