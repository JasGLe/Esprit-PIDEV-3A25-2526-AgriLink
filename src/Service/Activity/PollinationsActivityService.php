<?php

namespace App\Service\Activity;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PollinationsActivityService
{
    private const POLLINATIONS_TEXT_BASE = 'https://text.pollinations.ai/';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function generateRecommendations(string $prompt): array
    {
        $url = self::POLLINATIONS_TEXT_BASE . rawurlencode($prompt);

        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 35,
            'max_duration' => 35,
            'headers' => [
                'Accept' => 'application/json, text/plain;q=0.9',
                'User-Agent' => 'Symfony ActivityRecommendations/1.0',
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf('Pollinations indisponible (HTTP %d).', $statusCode));
        }

        $body = trim((string) $response->getContent(false));
        if ($body === '') {
            throw new \RuntimeException('Réponse Pollinations vide.');
        }

        $jsonText = $this->extractJson($body);
        if ($jsonText === null) {
            throw new \RuntimeException('Réponse Pollinations non exploitable (JSON introuvable).');
        }

        $decoded = json_decode($jsonText, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Réponse Pollinations invalide: ' . json_last_error_msg());
        }

        return $decoded;
    }

    private function extractJson(string $text): ?string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        if ($trimmed[0] === '{' && str_ends_with($trimmed, '}')) {
            return $trimmed;
        }

        if (preg_match('/```(?:json)?\s*(\{[\s\S]*\})\s*```/i', $trimmed, $matches) === 1) {
            return trim($matches[1]);
        }

        if (preg_match('/\{[\s\S]*\}/', $trimmed, $matches) === 1) {
            return trim($matches[0]);
        }

        return null;
    }
}