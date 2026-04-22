<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ForumAiAssistantService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $groqApiKey,
        private readonly string $groqApiBaseUrl,
        private readonly string $groqModel,
    ) {
    }

    public function ask(string $question): string
    {
        $normalizedQuestion = trim($question);

        if ($normalizedQuestion === '' || mb_strlen($normalizedQuestion) > 500) {
            throw new ForumAiAssistantException('Question invalide.', Response::HTTP_BAD_REQUEST);
        }

        if (trim($this->groqApiKey) === '') {
            throw new ForumAiAssistantException('Cle Groq absente pour l assistant agricole.', Response::HTTP_BAD_GATEWAY);
        }

        try {
            $response = $this->httpClient->request('POST', $this->buildChatCompletionsUrl(), [
                'headers' => [
                    'Authorization' => 'Bearer ' . trim($this->groqApiKey),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => trim($this->groqModel),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->buildSystemPrompt(),
                        ],
                        [
                            'role' => 'user',
                            'content' => $normalizedQuestion,
                        ],
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 450,
                ],
                'proxy' => null,
            ]);

            $statusCode = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            throw new ForumAiAssistantException('Impossible de joindre Groq. Verifiez la connexion reseau ou le proxy.', Response::HTTP_BAD_GATEWAY);
        }

        if ($statusCode !== Response::HTTP_OK) {
            $errorMessage = trim((string) ($payload['error']['message'] ?? ''));

            if ($statusCode === Response::HTTP_TOO_MANY_REQUESTS) {
                throw new ForumAiAssistantException('Limite Groq atteinte. Reessayez dans un moment.', Response::HTTP_TOO_MANY_REQUESTS);
            }

            if ($statusCode === Response::HTTP_UNAUTHORIZED || $statusCode === Response::HTTP_FORBIDDEN) {
                $message = $errorMessage !== ''
                    ? 'Acces Groq refuse: ' . $errorMessage
                    : 'Acces Groq refuse. Verifiez la cle API, le billing et l acces au modele.';

                throw new ForumAiAssistantException($message, $statusCode);
            }

            throw new ForumAiAssistantException(
                $errorMessage !== '' ? $errorMessage : 'Le service Groq ne repond pas correctement.',
                Response::HTTP_BAD_GATEWAY
            );
        }

        $answer = trim((string) ($payload['choices'][0]['message']['content'] ?? ''));
        $answer = preg_replace('/^```(?:text)?\s*/u', '', $answer) ?? $answer;
        $answer = preg_replace('/\s*```$/u', '', $answer) ?? $answer;
        $answer = trim($answer);

        if ($answer === '') {
            throw new ForumAiAssistantException('La reponse Groq est vide.', Response::HTTP_BAD_GATEWAY);
        }

        return $answer;
    }

    private function buildChatCompletionsUrl(): string
    {
        $baseUrl = rtrim($this->groqApiBaseUrl, '/');

        if (str_ends_with($baseUrl, '/v1')) {
            return $baseUrl . '/chat/completions';
        }

        return $baseUrl . '/chat/completions';
    }

    private function buildSystemPrompt(): string
    {
        return 'Tu es l assistant agricole du forum AgriLink. '
            . 'Reponds uniquement aux questions agricoles: cultures, sols, irrigation, maladies, fertilisation, rendement, rentabilite agricole, meteo agricole, equipements agricoles et bonnes pratiques de ferme. '
            . 'Si la question ne concerne pas l agriculture, refuse poliment en une phrase et invite l utilisateur a poser une question agricole. '
            . 'Reponds toujours en francais, avec un ton simple pour agriculteurs. '
            . 'Donne des reponses courtes, pratiques et prudentes. '
            . 'Ne donne pas de diagnostic definitif sans observation terrain; conseille de consulter un technicien/agronome local quand il y a un risque important.';
    }
}
