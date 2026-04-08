<?php

namespace App\SummaryGeneration\SummaryRequest;

readonly class SummaryRequestContext
{
    public function __construct(
        public string $type,
        public string $entityId,
        public string $label
    ) {}

    /**
     * @return array<string, array<string, string>>
     */
    public function toLogContext(): array
    {
        return [
            'context' => [
                'type' => $this->type,
                'entityId' => $this->entityId,
                'label' => $this->label
            ]
        ];
    }
}
