<?php

declare(strict_types=1);

namespace App\Gateway\Impl;

use App\Entity\Issue;
use App\Exception\IssueSynchronizationFailedException;
use App\Gateway\IssueTrackerGateway;
use App\Transformer\FeatureToJiraCommentTransformer;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class JiraIssueTrackerClient implements IssueTrackerGateway
{
    private HttpClientInterface $client;

    public function __construct(
        private FeatureToJiraCommentTransformer $featureToJiraCommentTransformer,
        string $apiUrl,
        string $accessToken,
        string $userIdentifier
    ) {
        $this->client = HttpClient::createForBaseUri($apiUrl, [
            'headers' => [
                'Content-type' => 'application/json',
                'Authorization' => sprintf('Basic %s', base64_encode("$userIdentifier:$accessToken"))
            ]
        ]);
    }

    public function sync(Issue $issue): void
    {
        $content = $this->featureToJiraCommentTransformer->transform($issue->feature);
        $issueIdentifier = preg_replace('#https://.+/browse/([A-Z0-9-]+)#', '$1', $issue->link);

        $previousContent = $this->cleanDescriptionFromPanels($this->getCurrentDescription($issueIdentifier));

        try {
            $this
                ->client
                ->request('PUT', "/rest/api/latest/issue/$issueIdentifier", [
                    'body' => json_encode([
                        'fields' => [
                            'description' => str_replace('__REPLACE__', $content, $previousContent)
                        ]
                    ])
                ]);
        } catch (TransportExceptionInterface $e) {
            throw new IssueSynchronizationFailedException($e->getMessage());
        }
    }

    private function getCurrentDescription(string $issueIdentifier): string
    {
        try {
            $issueContent = $this
                ->client
                ->request('GET', "/rest/api/latest/issue/$issueIdentifier")
                ->getContent();

            return json_decode($issueContent, true, 512, JSON_THROW_ON_ERROR)['fields']['description'] ?? '';
        } catch (TransportExceptionInterface $e) {
            throw new IssueSynchronizationFailedException($e->getMessage());
        }
    }

    private function cleanDescriptionFromPanels(string $description): string
    {
        $cleaned = preg_replace('/\{panel:bgColor=#[a-f0-9]{6}\}.+\{panel\}\n*/s', '__REPLACE__', $description);

        if (!str_contains($cleaned, '__REPLACE__')) {
            $cleaned .= "\n\n__REPLACE__";
        }

        return $cleaned;
    }
}
