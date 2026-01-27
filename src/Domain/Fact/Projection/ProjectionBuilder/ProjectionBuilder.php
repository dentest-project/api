<?php

declare(strict_types=1);

namespace App\Domain\Fact\Projection\ProjectionBuilder;

use App\Domain\Fact\Event\EventGateway;
use App\Domain\Fact\Projection\EventProcessor\EventProcessor;
use App\Domain\Fact\Projection\Projection;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * @template P of Projection
 */
abstract readonly class ProjectionBuilder
{
    /**
     * @return class-string<P>
     */
    abstract public function appliesTo(): string;

    /**
     * @var array<\App\Domain\Fact\Projection\EventProcessor\EventProcessor<P>>
     */
    private array $processors;

    /**
     * @param iterable<\App\Domain\Fact\Projection\EventProcessor\EventProcessor> $processors
     */
    public function __construct(
        #[AutowireIterator(EventProcessor::class)]
        iterable $processors,
        private EventGateway $eventGateway
    ) {
        $filteredProcessors = [];

        foreach ($processors as $processor) {
            if ($processor->appliesToProjection() === $this->appliesTo()) {
                $filteredProcessors[$processor->appliesToEvent()] = $processor;
            }
        }

        $this->processors = $filteredProcessors;
    }

    /**
     * @return P
     *
     * @throws \App\Domain\Fact\Error\InvalidProcessorForEventError
     * @throws \App\Domain\Fact\Error\InvalidProcessorForProjectionError
     */
    public function build(string $streamId): Projection
    {
        $projectionClass = $this->appliesTo();
        $baseProjection = new $projectionClass();

        $events = $this->eventGateway->findOrderedByStreamId($streamId);

        foreach ($events as $event) {
            if (!isset($this->processors[$event::class])) {
                continue;
            }

            $this->processors[$event::class]->processEvent($event, $baseProjection);
        }

        return $baseProjection;
    }
}
