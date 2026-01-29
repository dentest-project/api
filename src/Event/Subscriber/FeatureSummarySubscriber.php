<?php

namespace App\Event\Subscriber;

use App\FeatureSummary\FeatureSummaryQueue;
use App\FeatureSummary\FeatureSummaryUpdater;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

readonly class FeatureSummarySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private FeatureSummaryQueue $queue,
        private FeatureSummaryUpdater $featureSummaryUpdater,
        private LoggerInterface $logger
    ) {}

    public function onKernelTerminate(): void
    {
        foreach ($this->queue->drain() as $featureId) {
            try {
                $this->featureSummaryUpdater->update($featureId);
            } catch (Throwable $exception) {
                $this->logger->error('Failed to update feature summary.', [
                    'featureId' => $featureId,
                    'exception' => $exception
                ]);
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
