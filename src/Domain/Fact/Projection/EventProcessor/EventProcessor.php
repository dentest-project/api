<?php

declare(strict_types=1);

namespace App\Domain\Fact\Projection\EventProcessor;

use App\Domain\Fact\Error\InvalidProcessorForEventError;
use App\Domain\Fact\Error\InvalidProcessorForProjectionError;
use App\Domain\Fact\Event\Event;
use App\Domain\Fact\Projection\Projection;

/**
 * @template P of Projection
 * @template E of Event
 */
abstract readonly class EventProcessor
{
    /**
     * @return class-string<E>
     */
    abstract public function appliesToEvent(): string;

    /**
     * @return class-string<P>
     */
    abstract public function appliesToProjection(): string;

    /**
     * @param E $event
     * @param P $projection
     */
    abstract protected function process(Event $event, Projection $projection): void;

    /**
     * @param E $event
     * @param P $projection
     *
     * @throws \App\Domain\Fact\Error\InvalidProcessorForEventError
     * @throws \App\Domain\Fact\Error\InvalidProcessorForProjectionError
     */
    public final function processEvent(Event $event, Projection $projection): void {
        if (!is_a($projection, $this->appliesToProjection())) {
            throw new InvalidProcessorForProjectionError();
        }

        if (!is_a($event, $this->appliesToEvent())) {
            throw new InvalidProcessorForEventError();
        }

        $this->process($event, $projection);
    }
}
