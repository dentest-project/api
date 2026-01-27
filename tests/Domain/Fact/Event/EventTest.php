<?php

declare(strict_types=1);

namespace App\Tests\Domain\Fact\Event;

use App\Tests\Double\Stub\Domain\Fact\Event\EventStub;
use DateTime;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function testGetNameReturnsClassNameBasedEvent(): void
    {
        $this->assertEquals('EVENT_STUB', (new EventStub(
            'id', new DateTime(), 'stream', null
        ))->getName());
    }

    public function testGetHappenedAtReturnsAccurateDate(): void
    {
        $this->assertEquals('2020-01-01 00:00:00', (new EventStub(
            'id', DateTime::createFromFormat('Y-m-d H:i:s', '2020-01-01 00:00:00'), 'stream', null
        ))->happenedAt->format('Y-m-d H:i:s'));
    }
}
