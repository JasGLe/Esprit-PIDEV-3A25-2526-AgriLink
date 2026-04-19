<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ForumVoiceTranscriptionService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $geminiApiKey,
        private readonly string $geminiApiBaseUrl,
        private readonly string $geminiModel,
    ) {
    }

    public function transcribe(string $audioPath, string $mimeType): string
    {
        if (trim($this->geminiApiKey) === '') {
            throw new ForumMessageTranslationException('Cle Gemini absente pour la transcription.', Response::HTTP_BAD_GATEWAY);
        }

        if (!is_file($audioPath) || !is_readable($audioPath)) {
            throw new ForumMessageTranslationException('Fichier audio introuvable.', Response::HTTP_NOT_FOUND);
        }

        $audioBytes = file_get_contents($audioPath);
        if ($audioBytes === false || $audioBytes === '') {
            throw new ForumMessageTranslationException('Impossible de lire le message vocal.', Response::HTTP_BAD_GATEWAY);
        }

        $response = $this->httpClient->request(
            'POST',
            rtrim($this->geminiApiBaseUrl, '/') . '/v1beta/models/' . rawurlencode($this->geminiModel) . ':generateContent',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => trim($this->geminiApiKey),
                ],
                'json' => [
                    'system_instruction' => [
                        'parts' => [[
                            'text' => 'You are a speech transcription engine for a forum. '
                                . 'Transcribe the spoken audio faithfully in its original language. '
                                . 'Do not translate. Do not summarize. Do not explain. '
                                . 'Add natural punctuation when useful. Return only the transcript text.',
                        ]],
                    ],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [
                            [
                                'text' => 'Transcribe this forum voice message exactly.',
                            ],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => base64_encode($audioBytes),
                                ],
                            ],
                        ],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'topP' => 0.8,
                        'topK' => 20,
                        'maxOutputTokens' => 1024,
                    ],
                ],
            ]
        );

        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);

        if ($statusCode !== Response::HTTP_OK) {
            $errorMessage = trim((string) ($payload['error']['message'] ?? ''));

            if ($statusCode === Response::HTTP_TOO_MANY_REQUESTS) {
                throw new ForumMessageTranslationException('Limite Gemini atteinte. Reessayez dans un moment.', Response::HTTP_TOO_MANY_REQUESTS);
            }

            if ($statusCode === Response::HTTP_UNAUTHORIZED || $statusCode === Response::HTTP_FORBIDDEN) {
                throw new ForumMessageTranslationException('Cle Gemini invalide ou acces refuse.', Response::HTTP_BAD_GATEWAY);
            }

            throw new ForumMessageTranslationException(
                $errorMessage !== '' ? $errorMessage : 'Le service Gemini ne repond pas correctement.',
                Response::HTTP_BAD_GATEWAY
            );
        }

        $transcript = trim((string) ($payload['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        $transcript = preg_replace('/^```(?:text)?\s*/u', '', $transcript) ?? $transcript;
        $transcript = preg_replace('/\s*```$/u', '', $transcript) ?? $transcript;
        $transcript = trim($transcript);

        if ($transcript === '') {
            throw new ForumMessageTranslationException('La transcription est vide.', Response::HTTP_BAD_GATEWAY);
        }

        return $transcript;
    }
}
