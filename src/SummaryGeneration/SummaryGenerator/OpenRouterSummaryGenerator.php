<?php

namespace App\SummaryGeneration\SummaryGenerator;

use App\SummaryGeneration\SummaryRequest\SummaryRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

readonly class OpenRouterSummaryGenerator implements SummaryGenerator
{
    private const OPENROUTER_BASE_URI = 'https://openrouter.ai';
    private const OPENROUTER_APP_TITLE = 'Dentest API';
    private const CHAT_COMPLETIONS_PATH = '/api/v1/chat/completions';

    private HttpClientInterface $client;
    private bool $enabled;
    /** @var string[] */
    private array $fallbackModels;
    private int $maxRetries;
    private int $retryDelayMs;
    private int $maxRetryDelayMs;

    public function __construct(
        private LoggerInterface $logger,
        private string $model,
        string $apiKey,
        int $timeout,
        string $fallbackModels,
        int $maxRetries,
        int $retryDelayMs,
        int $maxRetryDelayMs
    ) {
        $this->enabled = $apiKey !== '' && $this->model !== '';
        $this->fallbackModels = $this->normalizeFallbackModels($fallbackModels);
        $this->maxRetries = max(0, $maxRetries);
        $this->retryDelayMs = max(0, $retryDelayMs);
        $this->maxRetryDelayMs = max($this->retryDelayMs, $maxRetryDelayMs);

        if (!$this->enabled) {
            $this->logger->error('Summary generation is missing configuration.', [
                'apiKeyConfigured' => $apiKey !== '',
                'model' => $model
            ]);
        }

        $this->client = $this->enabled
            ? HttpClient::createForBaseUri(self::OPENROUTER_BASE_URI, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => sprintf('Bearer %s', $apiKey),
                    'Content-Type' => 'application/json',
                    'X-Title' => self::OPENROUTER_APP_TITLE
                ],
                'timeout' => $timeout
            ])
            : HttpClient::create();
    }

    public function generate(SummaryRequest $request): ?string
    {
        static $rateLimitedUntil = 0.0;

        $logContext = $this->buildLogContext($request);

        if (!$this->enabled || $request->systemPrompt === '' || $request->userPrompt === '') {
            $this->logger->error('Summary generation failed.', array_merge($logContext, [
                'failure' => [
                    'reason' => !$this->enabled ? 'missing_configuration' : 'missing_prompt',
                    'enabled' => $this->enabled,
                    'systemPromptProvided' => $request->systemPrompt !== '',
                    'userPromptProvided' => $request->userPrompt !== ''
                ]
            ]));

            return null;
        }

        $now = microtime(true);
        if ($rateLimitedUntil > $now) {
            $remainingDelayMs = max(0, (int) ceil(($rateLimitedUntil - $now) * 1000));

            $this->logger->warning('Summary generation skipped because the OpenRouter cooldown is active.', array_merge($logContext, [
                'failure' => [
                    'reason' => 'rate_limit_cooldown',
                    'retryInMs' => $remainingDelayMs
                ]
            ]));

            return null;
        }

        $payload = [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $request->systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $request->userPrompt
                ]
            ],
            'model' => $this->model
        ];
        if (count($this->fallbackModels) > 0) {
            $payload['models'] = $this->fallbackModels;
        }

        $responsePayload = null;
        $summary = null;
        $statusCode = 0;
        $responseModel = null;
        $attemptCount = 0;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            $attemptCount = $attempt + 1;

            try {
                $response = $this->client->request('POST', self::CHAT_COMPLETIONS_PATH, [
                    'json' => $payload
                ]);
                $statusCode = (int) ($response->getInfo('http_code') ?? 0);
                $headers = $response->getHeaders(false);
                $rawBody = $response->getContent(false);
            } catch (Throwable $exception) {
                $this->logger->error('Summary generation failed.', array_merge($logContext, [
                    'failure' => [
                        'reason' => 'request_exception',
                        'attempt' => $attemptCount,
                        'message' => $exception->getMessage()
                    ],
                    'exception' => $exception
                ]));

                return null;
            }

            if ($this->isRetryableStatusCode($statusCode) && $attempt < $this->maxRetries) {
                $delayMs = $this->resolveRetryDelayMs($headers, $attempt);

                $this->logger->warning('Summary generation hit a retryable OpenRouter error and will be retried.', array_merge($logContext, [
                    'failure' => [
                        'reason' => $statusCode === 429 ? 'rate_limited' : 'upstream_error',
                        'statusCode' => $statusCode,
                        'responseBody' => $rawBody,
                        'attempt' => $attemptCount,
                        'retryInMs' => $delayMs
                    ]
                ]));

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }

                continue;
            }

            if ($statusCode >= 400) {
                $failureContext = [
                    'reason' => $statusCode === 429 ? 'rate_limited' : 'http_error',
                    'statusCode' => $statusCode,
                    'responseBody' => $rawBody,
                    'attempt' => $attemptCount
                ];

                if ($statusCode === 429) {
                    $cooldownMs = $this->resolveRetryDelayMs($headers, $attempt);
                    if ($cooldownMs > 0) {
                        $rateLimitedUntil = microtime(true) + ($cooldownMs / 1000);
                        $failureContext['retryInMs'] = $cooldownMs;
                    }
                }

                $this->logger->error('Summary generation failed.', array_merge($logContext, [
                    'failure' => $failureContext
                ]));

                return null;
            }

            try {
                $responsePayload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                $this->logger->error('Summary generation failed.', array_merge($logContext, [
                    'failure' => [
                        'reason' => 'invalid_json',
                        'responseBody' => $rawBody,
                        'attempt' => $attemptCount,
                        'message' => $exception->getMessage()
                    ],
                    'exception' => $exception
                ]));

                return null;
            }

            if (!is_array($responsePayload)) {
                $this->logger->error('Summary generation failed.', array_merge($logContext, [
                    'failure' => [
                        'reason' => 'unexpected_payload_type',
                        'payloadType' => get_debug_type($responsePayload),
                        'responseBody' => $rawBody,
                        'attempt' => $attemptCount
                    ]
                ]));

                return null;
            }

            $summary = trim($this->extractSummary($responsePayload));
            $responseModel = is_string($responsePayload['model'] ?? null) ? $responsePayload['model'] : null;
            break;
        }

        if ($summary === null) {
            $this->logger->error('Summary generation failed.', array_merge($logContext, [
                'failure' => [
                    'reason' => 'no_response_after_retries',
                    'attempts' => $attemptCount
                ]
            ]));

            return null;
        }

        if ($summary === '') {
            $this->logger->error('Summary generation failed.', array_merge($logContext, [
                'failure' => [
                    'reason' => 'empty_summary',
                    'responseBody' => json_encode($responsePayload),
                    'attempts' => $attemptCount
                ]
            ]));

            return null;
        }

        $this->logger->info('Summary generated.', array_merge($logContext, [
            'httpStatus' => $statusCode,
            'attempts' => $attemptCount,
            'responseModel' => $responseModel,
            'output' => $summary
        ]));

        return $summary;
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

    /**
     * @return string[]
     */
    private function normalizeFallbackModels(string $fallbackModels): array
    {
        $models = array_filter(array_map(
            static fn (string $model): string => trim($model),
            explode(',', $fallbackModels)
        ));

        return array_values(array_unique(array_filter($models, fn (string $model): bool => $model !== '' && $model !== $this->model)));
    }

    private function isRetryableStatusCode(int $statusCode): bool
    {
        return $statusCode === 429 || $statusCode >= 500;
    }

    /**
     * @param array<string, array<int, string>> $headers
     */
    private function resolveRetryDelayMs(array $headers, int $attempt): int
    {
        $retryAfterSeconds = $this->extractRetryAfterSeconds($headers);
        if ($retryAfterSeconds !== null) {
            return min((int) ceil($retryAfterSeconds * 1000), $this->maxRetryDelayMs);
        }

        $exponentialDelay = $this->retryDelayMs * (2 ** $attempt);

        return min($exponentialDelay, $this->maxRetryDelayMs);
    }

    /**
     * @param array<string, array<int, string>> $headers
     */
    private function extractRetryAfterSeconds(array $headers): ?float
    {
        $retryAfter = $headers['retry-after'][0] ?? null;
        if (!is_string($retryAfter) || trim($retryAfter) === '') {
            return null;
        }

        if (is_numeric($retryAfter)) {
            return max(0.0, (float) $retryAfter);
        }

        $timestamp = strtotime($retryAfter);
        if ($timestamp === false) {
            return null;
        }

        return max(0.0, $timestamp - time());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLogContext(SummaryRequest $request): array
    {
        return array_merge($request->context->toLogContext(), [
            'provider' => 'openrouter',
            'model' => $this->model,
            'fallbackModels' => $this->fallbackModels,
            'prompt' => [
                'system' => $request->systemPrompt,
                'user' => $request->userPrompt
            ]
        ]);
    }
}
