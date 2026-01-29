<?php

namespace App\FeatureSummary;

use App\Entity\Feature;
use App\Entity\Scenario;
use App\Transformer\FeatureToStringTransformer;
use Doctrine\Common\Collections\Collection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

readonly class FeatureSummaryGenerator
{
    private HttpClientInterface $client;
    private string $baseUrl;
    private string $model;
    private bool $enabled;
    private int $maxInputChars;
    private int $maxOutputChars;
    private int $projectContextMaxChars;
    private int $projectFeatureMaxChars;
    private float $temperature;
    private int $numPredict;
    private int $streamTimeout;
    private bool $includeProjectContext;
    private int $numCtx;

    public function __construct(
        private FeatureToStringTransformer $featureToStringTransformer,
        private LoggerInterface $logger,
        string $baseUrl,
        string $model,
        int $timeout,
        int $streamTimeout,
        int $maxInputChars,
        int $maxOutputChars,
        int $projectContextMaxChars,
        int $projectFeatureMaxChars,
        float $temperature,
        int $numPredict,
        bool $includeProjectContext,
        int $numCtx
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model;
        $this->maxInputChars = $maxInputChars;
        $this->maxOutputChars = $maxOutputChars;
        $this->projectContextMaxChars = $projectContextMaxChars;
        $this->projectFeatureMaxChars = $projectFeatureMaxChars;
        $this->temperature = $temperature;
        $this->numPredict = $numPredict;
        $this->streamTimeout = $streamTimeout;
        $this->includeProjectContext = $includeProjectContext;
        $this->numCtx = $numCtx;

        $this->enabled = $this->baseUrl !== '' && $this->model !== '';

        if (!$this->enabled) {
            $this->logger->error('Feature summary generation is missing configuration.', [
                'baseUrl' => $baseUrl,
                'model' => $model
            ]);
        }

        $this->client = $this->enabled
            ? HttpClient::createForBaseUri($this->baseUrl, [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'timeout' => $timeout
            ])
            : HttpClient::create();
    }

    /**
     * @param Feature[] $projectFeatures
     */
    public function generate(Feature $feature, array $projectFeatures = []): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $featureText = $this->featureToText($feature);
        if ($featureText === '') {
            return null;
        }

        $contextText = $this->buildProjectContext($projectFeatures, $feature);
        $prompt = $this->buildPrompt($featureText, $contextText);

        try {
            $options = [
                'temperature' => $this->temperature,
                'num_predict' => $this->numPredict
            ];

            if ($this->numCtx > 0) {
                $options['num_ctx'] = $this->numCtx;
            }

            $response = $this->client->request('POST', '/api/generate', [
                'json' => [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'stream' => true,
                    'options' => $options
                ]
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Failed to generate feature summary.', [
                'featureId' => $feature->id,
                'exception' => $exception
            ]);

            return null;
        }

        $rawBody = '';
        $summary = trim($this->readStreamedSummary($response, $feature, $rawBody));
        $statusCode = (int) ($response->getInfo('http_code') ?? 0);

        if ($statusCode >= 400) {
            $this->logger->error('Feature summary request failed.', [
                'featureId' => $feature->id,
                'statusCode' => $statusCode,
                'body' => $this->truncate($rawBody, 1000)
            ]);

            return null;
        }

        if ($summary === '') {
            return null;
        }

        return $this->truncate($summary, $this->maxOutputChars);
    }

    private function featureToText(Feature $feature): string
    {
        $this->featureToStringTransformer->setInlineParameterWrapper('');
        $text = $this->featureToStringTransformer->transform($feature);

        return $this->truncate($text, $this->maxInputChars);
    }

    /**
     * @param Feature[] $projectFeatures
     */
    private function buildProjectContext(array $projectFeatures, Feature $editedFeature): string
    {
        if (!$this->includeProjectContext || count($projectFeatures) === 0) {
            return '';
        }

        $blocks = [];
        $remaining = $this->projectContextMaxChars;

        foreach ($projectFeatures as $projectFeature) {
            if (!$projectFeature instanceof Feature) {
                continue;
            }

            if ($projectFeature->id === $editedFeature->id) {
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
        $scenarioTitles = [];

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
            $title = trim($scenario->title);
            $scenarioTitles[] = $title !== '' ? sprintf('%s: %s', $label, $title) : $label;
        }

        if (count($scenarioTitles) > 0) {
            $block .= "\n" . implode("\n", array_map(static fn (string $title): string => sprintf('  - %s', $title), $scenarioTitles));
        }

        return $this->truncate($block, $this->projectFeatureMaxChars);
    }

    private function buildPrompt(string $featureText, string $contextText): string
    {
        if ($contextText !== '') {
            return sprintf(
<<<PROMPT
You are given all project items as context. Use them only to disambiguate the edited feature.

Project items (excluding the edited one), with their scenario titles:
%s

Edited feature (Gherkin):
%s

Summarize the edited feature in 3-7 sentences as a plain paragraph.
Do NOT output Gherkin or any headings/bullets.
Avoid starting lines with Gherkin keywords (Feature, Scenario, Outline, Background, Given, When, Then, And, But).
Do NOT use technical words.
Do NOT describe the feature as it is intended to be a test. It is a feature description.
Describe this feature in a very natural language for a human who doesn't understand technical specificities.
Keep it under %d characters.
PROMPT,
                $contextText,
                $featureText,
                $this->maxOutputChars
            );
        }

        return sprintf(
<<<PROMPT
Edited feature (Gherkin):
%s

Summarize the edited feature in 3-7 sentences as a plain paragraph.
Do NOT output Gherkin or any headings/bullets.
Avoid starting lines with Gherkin keywords (Feature, Scenario, Outline, Background, Given, When, Then, And, But).
Do NOT use technical words.
Do NOT describe the feature as a test.
Describe this feature in a very natural language for a human who doesn't understand technical specificities.
Keep it under %d characters.
PROMPT,
            $this->maxOutputChars,
            $featureText
        );
    }

    private function extractSummary(array $payload): string
    {
        if (isset($payload['response']) && is_string($payload['response'])) {
            return $payload['response'];
        }

        if (isset($payload['text']) && is_string($payload['text'])) {
            return $payload['text'];
        }

        if (isset($payload['choices'][0]['message']['content']) && is_string($payload['choices'][0]['message']['content'])) {
            return $payload['choices'][0]['message']['content'];
        }

        if (isset($payload['choices'][0]['text']) && is_string($payload['choices'][0]['text'])) {
            return $payload['choices'][0]['text'];
        }

        return '';
    }

    private function readStreamedSummary(ResponseInterface $response, Feature $feature, ?string &$rawBody = null): string
    {
        $summary = '';
        $buffer = '';
        $rawBody = '';

        try {
            foreach ($this->client->stream($response, $this->streamTimeout) as $chunk) {
                if ($chunk->isTimeout()) {
                    continue;
                }

                $buffer .= $chunk->getContent();

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $pos));
                    $buffer = substr($buffer, $pos + 1);

                    if ($line === '') {
                        continue;
                    }

                    $rawBody .= $line . "\n";
                    $done = $this->applyStreamLine($line, $summary, $feature);

                    if (mb_strlen($summary) >= $this->maxOutputChars) {
                        $summary = $this->truncate($summary, $this->maxOutputChars);
                        $response->cancel();

                        return $summary;
                    }

                    if ($done) {
                        return $summary;
                    }
                }
            }
        } catch (Throwable $exception) {
            $this->logger->error('Failed to read streamed feature summary.', [
                'featureId' => $feature->id,
                'exception' => $exception
            ]);

            return '';
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $rawBody .= $tail . "\n";
            $this->applyStreamLine($tail, $summary, $feature);
        }

        return $summary;
    }

    private function applyStreamLine(string $line, string &$summary, Feature $feature): bool
    {
        try {
            $payload = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $this->logger->error('Feature summary stream payload was not valid JSON.', [
                'featureId' => $feature->id,
                'exception' => $exception,
                'body' => $this->truncate($line, 1000)
            ]);

            return false;
        }

        if (!is_array($payload)) {
            $this->logger->error('Feature summary stream payload was not an array.', [
                'featureId' => $feature->id
            ]);

            return false;
        }

        $chunkText = $this->extractSummary($payload);
        if ($chunkText !== '') {
            $summary .= $chunkText;
        }

        return !empty($payload['done']);
    }

    private function truncate(string $value, int $limit): string
    {
        if ($limit <= 0) {
            return '';
        }

        return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit);
    }
}
