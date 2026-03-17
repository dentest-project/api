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

    public function __construct(
        private LoggerInterface $logger,
        private string $model,
        string $apiKey,
        int $timeout
    ) {
        $this->enabled = $apiKey !== '' && $this->model !== '';

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
        if (!$this->enabled || $request->systemPrompt === '' || $request->userPrompt === '') {
            return null;
        }

        try {
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

            $response = $this->client->request('POST', self::CHAT_COMPLETIONS_PATH, [
                'json' => $payload
            ]);
            $statusCode = (int) ($response->getInfo('http_code') ?? 0);
            $rawBody = $response->getContent(false);
        } catch (Throwable $exception) {
            $this->logger->error('Failed to generate summary.', [
                'exception' => $exception
            ]);

            return null;
        }

        if ($statusCode >= 400) {
            $this->logger->error('Summary request failed.', [
                'statusCode' => $statusCode,
                'body' => $rawBody
            ]);

            return null;
        }

        try {
            $responsePayload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $this->logger->error('Summary response was not valid JSON.', [
                'exception' => $exception,
                'body' => $rawBody
            ]);

            return null;
        }

        if (!is_array($responsePayload)) {
            $this->logger->error('Summary response was not an array.');

            return null;
        }

        $summary = trim($this->extractSummary($responsePayload));

        return $summary !== '' ? $summary : null;
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
}
