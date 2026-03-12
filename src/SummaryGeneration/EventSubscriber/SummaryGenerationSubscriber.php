<?php

namespace App\SummaryGeneration\EventSubscriber;

use App\SummaryGeneration\Contract\SummaryUpdater;
use App\SummaryGeneration\Queue\SummaryUpdateQueue;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

readonly class SummaryGenerationSubscriber implements EventSubscriberInterface
{
    /**
     * @param iterable<int, SummaryUpdater> $updaters
     */
    public function __construct(
        private SummaryUpdateQueue $summaryUpdateQueue,
        private iterable $updaters,
        private LoggerInterface $logger
    ) {}

    public function onKernelTerminate(): void
    {
        foreach ($this->summaryUpdateQueue->drain() as $summaryUpdate) {
            foreach ($this->updaters as $updater) {
                if (!$updater->supports($summaryUpdate->target)) {
                    continue;
                }

                try {
                    $updater->update($summaryUpdate);
                } catch (Throwable $exception) {
                    $this->logger->error('Failed to update summary.', [
                        'target' => $summaryUpdate->target->value,
                        'entityId' => $summaryUpdate->entityId,
                        'exception' => $exception
                    ]);
                }

                continue 2;
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onKernelTerminate'
        ];
    }
}
