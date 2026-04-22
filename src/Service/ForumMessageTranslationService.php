<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ForumMessageTranslationService
{
    private const TARGET_LABELS = [
        'fr' => 'Francais',
        'en' => 'Anglais',
        'ar' => 'Arabe',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $geminiApiKey,
        private readonly string $geminiApiBaseUrl,
        private readonly string $geminiModel,
    ) {
    }

    /**
     * @return array{translation: string, target: string, targetLabel: string, source: string}
     */
    public function translate(string $text, string $targetLanguage): array
    {
        $normalizedText = trim($text);
        $normalizedTarget = strtolower(trim($targetLanguage));

        if (
            $normalizedText === ''
            || !isset(self::TARGET_LABELS[$normalizedTarget])
            || trim($this->geminiApiKey) === ''
        ) {
            throw new ForumMessageTranslationException('Parametres de traduction invalides.', Response::HTTP_BAD_REQUEST);
        }

        $sourceLanguage = $this->detectLanguage($normalizedText);
        if ($sourceLanguage === $normalizedTarget) {
            return [
                'translation' => $normalizedText,
                'target' => $normalizedTarget,
                'targetLabel' => self::TARGET_LABELS[$normalizedTarget],
                'source' => $sourceLanguage,
            ];
        }

        $translatedText = $this->requestTranslation($normalizedText, $normalizedTarget);

        if ($translatedText === null || $this->textsAreEquivalent($normalizedText, $translatedText)) {
            throw new ForumMessageTranslationException('La traduction retournee est vide ou identique au texte original.', Response::HTTP_BAD_GATEWAY);
        }

        return [
            'translation' => $translatedText,
            'target' => $normalizedTarget,
            'targetLabel' => self::TARGET_LABELS[$normalizedTarget],
            'source' => $sourceLanguage,
        ];
    }

    private function requestTranslation(string $text, string $targetLanguage): ?string
    {
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
                            'text' => $this->buildSystemInstruction(),
                        ]],
                    ],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [[
                            'text' => $this->buildUserPrompt($text, $targetLanguage),
                        ]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'topP' => 0.8,
                        'topK' => 20,
                        'maxOutputTokens' => 512,
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

        $translatedText = trim((string) ($payload['candidates'][0]['content']['parts'][0]['text'] ?? ''));

        if ($translatedText === '') {
            return null;
        }

        $translatedText = preg_replace('/^```(?:text)?\s*/u', '', $translatedText) ?? $translatedText;
        $translatedText = preg_replace('/\s*```$/u', '', $translatedText) ?? $translatedText;

        return trim($translatedText);
    }

    private function buildSystemInstruction(): string
    {
        return 'You are a translation engine for a forum. Translate only the user message into the requested target language. '
            . 'Do not explain. Do not comment. Do not add quotes. Do not add transliteration. '
            . 'Preserve intent, tone, punctuation, emojis, and line breaks when possible. '
            . 'Return only the translated text.';
    }

    private function buildUserPrompt(string $text, string $targetLanguage): string
    {
        return "Target language: {$targetLanguage}\n"
            . "Translate this forum message:\n"
            . $text;
    }

    private function detectLanguage(string $text): string
    {
        if (preg_match('/\p{Arabic}/u', $text) === 1) {
            return 'ar';
        }

        $normalized = mb_strtolower($text, 'UTF-8');
        $frenchMarkers = [' je ', ' tu ', ' il ', ' elle ', ' nous ', ' vous ', ' pas ', ' des ', ' les ', ' un ', ' une ', ' et ', ' de ', ' du ', 'bonjour', 'merci'];
        $englishMarkers = [' i ', ' you ', ' he ', ' she ', ' we ', ' they ', ' the ', ' and ', ' is ', ' are ', ' hello ', ' thanks ', ' this ', ' that '];

        $frenchScore = $this->countMarkers(' ' . $normalized . ' ', $frenchMarkers);
        $englishScore = $this->countMarkers(' ' . $normalized . ' ', $englishMarkers);

        return $frenchScore >= $englishScore ? 'fr' : 'en';
    }

    /**
     * @param list<string> $markers
     */
    private function countMarkers(string $text, array $markers): int
    {
        $score = 0;

        foreach ($markers as $marker) {
            if (str_contains($text, $marker)) {
                $score++;
            }
        }

        return $score;
    }

    private function textsAreEquivalent(string $left, string $right): bool
    {
        $normalize = static function (string $value): string {
            $value = mb_strtolower(trim($value), 'UTF-8');
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

            return $value;
        };

        return $normalize($left) === $normalize($right);
    }
}
