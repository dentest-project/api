<?php

declare(strict_types=1);

namespace App\Domain\Fact\Event;

use App\Entity\ScenarioStepAdverb;

final readonly class ScenarioStepAdded extends Event
{
    /**
     * @param array<\App\Domain\Fact\Event\EventItem\NewInlineStepParam|\App\Domain\Fact\Event\EventItem\NewMultilineStepParam|\App\Domain\Fact\Event\EventItem\NewTableStepParam> $params
     */
    public function __construct(
        EventMetadata $metadata,
        public string $scenarioId,
        public string $stepId,
        public string $scenarioStepId,
        public ScenarioStepAdverb $adverb,
        public array $params
    ) {
        parent::__construct($metadata);
    }
}
