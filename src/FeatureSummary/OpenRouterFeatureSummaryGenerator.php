<?php

namespace App\FeatureSummary;

use App\Entity\Feature;
use App\Entity\Path;
use App\Entity\Scenario;
use Doctrine\Common\Collections\Collection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

readonly class OpenRouterFeatureSummaryGenerator implements FeatureSummaryGenerator
{
    private const OPENROUTER_BASE_URI = 'https://openrouter.ai';

    private HttpClientInterface $client;

    public function __construct(
        private  LoggerInterface $logger,
        private string $model,
        private int $projectContextMaxChars,
        private int $projectFeatureMaxChars,
        string $apiKey,
        int $timeout
    ) {
        $this->client = HttpClient::createForBaseUri(self::OPENROUTER_BASE_URI, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => sprintf('Bearer %s', $apiKey),
                'Content-Type' => 'application/json'
            ],
            'timeout' => $timeout
        ]);
    }

    /**
     * @param Feature[] $projectFeatures
     */
    public function generate(Feature $feature, array $projectFeatures = []): ?string
    {
        $featureText = $this->featureToPromptText($feature);

        if ($featureText === '') {
            return null;
        }

        $contextText = $this->buildProjectContext($projectFeatures, $feature);

        try {
            $payload = [
                'messages' => $this->buildMessages($featureText, $contextText),
                'model' => $this->model,
            ];

            $this->logger->info(json_encode($payload, JSON_THROW_ON_ERROR));

            $response = $this->client->request('POST', '/api/v1/chat/completions', [
                'json' => $payload
            ]);

            $rawBody = $response->getContent();
        } catch (Throwable $exception) {
            $this->logger->error('Failed to generate feature summary.', [
                'featureId' => $feature->id,
                'exception' => $exception
            ]);

            return null;
        }

        try {
            $responsePayload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $this->logger->error('Feature summary response was not valid JSON.', [
                'featureId' => $feature->id,
                'exception' => $exception,
                'body' => $rawBody,
                'statusCode' => $response->getStatusCode()
            ]);

            return null;
        }

        if (!is_array($responsePayload)) {
            $this->logger->error('Feature summary response was not an array.', [
                'featureId' => $feature->id
            ]);

            return null;
        }

        $summary = $this->extractSummary($responsePayload);

        if ($summary === '') {
            return null;
        }

        return $summary;
    }

    private function featureToPromptText(Feature $feature): string
    {
        $scenarios = $feature->scenarios instanceof Collection ? $feature->scenarios->toArray() : $feature->scenarios;
        $lines = [];

        foreach ($scenarios as $scenario) {
            if (!$scenario instanceof Scenario) {
                continue;
            }

            if ($scenario->type === Scenario::TYPE_BACKGROUND) {
                continue;
            }

            $label = match ($scenario->type) {
                Scenario::TYPE_OUTLINE => 'Outline',
                default => 'Scenario'
            };

            $lines[] = $scenario->title !== ''
                ? sprintf('%s: %s', $label, $scenario->title)
                : $label;
        }

        if (count($lines) === 0) {
            return '';
        }

        return sprintf(
            "Title: %s\nDescription: %s\nScenario titles:\n%s",
            $feature->title,
            $feature->description ? : '-',
            implode("\n", $lines)
        );
    }

    /**
     * @param Feature[] $projectFeatures
     */
    private function buildProjectContext(array $projectFeatures, Feature $editedFeature): string
    {
        if (count($projectFeatures) === 0) {
            return '';
        }

        $superPath = $editedFeature->path->parent ?? $editedFeature->path;
        $blocks = [];
        $remaining = $this->projectContextMaxChars;

        foreach ($projectFeatures as $projectFeature) {
            if (!$projectFeature instanceof Feature) {
                continue;
            }

            if ($projectFeature->id === $editedFeature->id) {
                continue;
            }

            if (!$this->isUnderPath($projectFeature->path ?? null, $superPath)) {
                continue;
            }

            $block = $this->featureToContextText($projectFeature);
            if ($block === '') {
                continue;
            }

            if ($remaining > 0 && mb_strlen($block) > $remaining) {
                $block = $this->truncate($block, $remaining);
                $blocks[] = $block;
                $remaining = 0;
                break;
            }

            $blocks[] = $block;
            $remaining -= mb_strlen($block);

            if ($remaining <= 0) {
                break;
            }
        }

        return implode("\n\n", $blocks);
    }

    private function featureToContextText(Feature $feature): string
    {
        $block = sprintf('Item: %s', $feature->title);
        $scenarios = $feature->scenarios instanceof Collection ? $feature->scenarios->toArray() : $feature->scenarios;
        $lines = [];

        foreach ($scenarios as $scenario) {
            if (!$scenario instanceof Scenario) {
                continue;
            }

            if ($scenario->type === Scenario::TYPE_BACKGROUND) {
                continue;
            }

            $label = match ($scenario->type) {
                Scenario::TYPE_OUTLINE => 'Outline',
                default => 'Scenario'
            };
            $lines[] = $scenario->title !== '' ? sprintf('%s: %s', $label, $scenario->title) : $label;
        }

        if (count($lines) > 0) {
            $block .= sprintf("\n  - %s", implode("\n  - ", $lines));
        }

        return $this->truncate($block, $this->projectFeatureMaxChars);
    }

    private function isUnderPath(?Path $path, ?Path $ancestor): bool
    {
        if ($ancestor === null) {
            return true;
        }

        $current = $path;
        while ($current !== null) {
            if ($current->id === $ancestor->id) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }

    private function buildMessages(string $featureText, string $contextText): array
    {
        return [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt()
            ],
            [
                'role' => 'user',
                'content' => $contextText !== ''
                    ? sprintf(
<<<PROMPT
You are given all project items as context. Use them only to disambiguate the edited feature.

Project items (excluding the edited one), with their scenario titles:
%s

Edited feature:
%s
PROMPT,
                        $contextText,
                        $featureText
                    )
                    : sprintf(
<<<PROMPT
Edited feature:
%s
PROMPT,
                        $featureText
                    )
            ]
        ];
    }

    private function buildSystemPrompt(): string
    {
        return <<<PROMPT
Summarize the edited feature in 3-7 sentences as a plain paragraph.
Do NOT output Gherkin or any headings or bullets.
Avoid starting lines with Gherkin keywords like Feature, Scenario, Outline, Background, Given, When, Then, And, or But.
Do NOT use technical words.
Do NOT describe the feature as a test.
Describe this feature in natural language for a human who does not understand technical specifics.
PROMPT;
    }

    private function extractSummary(array $payload): string
    {
        if (isset($payload['response']) && is_string($payload['response'])) {
            return $payload['response'];
        }

        if (isset($payload['text']) && is_string($payload['text'])) {
            return $payload['text'];
        }

        if (isset($payload['choices'][0]['message']['content'])) {
            return $this->normalizeMessageContent($payload['choices'][0]['message']['content']);
        }

        if (isset($payload['choices'][0]['text']) && is_string($payload['choices'][0]['text'])) {
            return $payload['choices'][0]['text'];
        }

        return '';
    }

    private function normalizeMessageContent(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (!is_array($content)) {
            return '';
        }

        if (isset($content['text']) && is_string($content['text'])) {
            return $content['text'];
        }

        if (isset($content['content']) && is_string($content['content'])) {
            return $content['content'];
        }

        $parts = [];
        foreach ($content as $part) {
            if (is_string($part)) {
                $parts[] = $part;
                continue;
            }

            if (!is_array($part)) {
                continue;
            }

            if (isset($part['text']) && is_string($part['text'])) {
                $parts[] = $part['text'];
                continue;
            }

            if (isset($part['content']) && is_string($part['content'])) {
                $parts[] = $part['content'];
            }
        }

        return implode('', $parts);
    }

    private function truncate(string $value, int $limit): string
    {
        if ($limit <= 0) {
            return '';
        }

        return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit);
    }
}
