<?php

namespace App\Service\Activity;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiActivityService
{
    private HttpClientInterface $client;
    private string $apiKey;

    public function __construct(HttpClientInterface $client, string $geminiApiKey1)
    {
        $this->client = $client;
        $this->apiKey = $geminiApiKey1;
    }

    public function recommanderCulture(array $parcelle): array
    {
        $prompt = $this->buildPrompt($parcelle);

        $response = $this->client->request(
            'POST',
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $this->apiKey,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $data = $response->toArray(false);

        if (isset($data['promptFeedback'])) {
            throw new \Exception("Gemini blocked: " . json_encode($data['promptFeedback']));
        }

        if (!isset($data['candidates'][0])) {
            throw new \Exception("Aucune candidate Gemini: " . json_encode($data));
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$text) {
            throw new \Exception("Réponse Gemini vide (parts missing): " . json_encode($data));
        }

        $text = trim($text);
        preg_match('/\{.*\}/s', $text, $matches);

        if (!isset($matches[0])) {
            throw new \Exception("JSON introuvable: " . $text);
        }

        $json = json_decode($matches[0], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON invalide: " . json_last_error_msg());
        }

        return $json;
    }

    private function buildPrompt(array $p): string
    {
        return "
Tu es un expert agronome spécialisé en agriculture tunisienne.

Réponds UNIQUEMENT en JSON valide.

FORMAT EXACT:
{
  \"analyse_sol\": \"...\",
  \"recommandations\": [
    {
      \"nom\": \"Olivier\",
      \"type\": \"OLEICULTURE\",
      \"compatibilite\": 85,
      \"explication\": \"...\",
      \"saison_ideale\": \"automne\",
      \"duree_mois\": 6
    }
  ],
  \"risques\": \"...\"
}

DONNÉES PARCELLE:
- Sol: {$p['typeSol']}
- Etat: {$p['etat']}
- Superficie: {$p['superficie']} ha
- Ville: {$p['ville']}
- Saison: {$p['saison']}

Réponds uniquement en JSON.
";
    }

    public function analyserImage($file, string $nomCulture = ''): array
    {
        try {
            $imageData = base64_encode(file_get_contents($file->getPathname()));
            $mimeType = $file->getMimeType();

            $response = $this->client->request(
                'POST',
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $this->apiKey,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => $this->buildImagePrompt($nomCulture)],
                                    [
                                        'inline_data' => [
                                            'mime_type' => $mimeType,
                                            'data' => $imageData,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]
            );

            $data = $response->toArray(false);

            if (isset($data['promptFeedback'])) {
                throw new \Exception('Gemini bloqué');
            }

            if (!isset($data['candidates'][0])) {
                throw new \Exception('Réponse Gemini vide');
            }

            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (!$text) {
                throw new \Exception('Réponse vide');
            }

            preg_match('/\{.*\}/s', $text, $matches);
            if (!isset($matches[0])) {
                throw new \Exception('JSON introuvable');
            }

            $json = json_decode($matches[0], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON invalide: ' . json_last_error_msg());
            }

            return $json;
        } catch (\Throwable $e) {
            return [
                'sante_globale' => 'MOYEN',
                'score_sante' => 50,
                'resume' => 'Erreur analyse IA',
                'stade_croissance' => 'Inconnu',
                'stress_hydrique' => [
                    'niveau' => 'INCONNU',
                    'description' => $e->getMessage(),
                ],
                'diagnostic_principal' => [
                    'probleme' => 'Erreur IA',
                    'description' => $e->getMessage(),
                    'confiance' => 0,
                ],
                'diagnostics_secondaires' => [],
                'recommandations' => [],
            ];
        }
    }

    private function buildImagePrompt(string $nomCulture = ''): string
    {
        return "
Tu es un expert en phytopathologie et agriculture.

Analyse cette image de culture agricole.

Culture (optionnel): {$nomCulture}

Réponds UNIQUEMENT en JSON valide:

{
  \"sante_globale\": \"BON | MOYEN | MAUVAIS | CRITIQUE\",
  \"score_sante\": 0-100,
  \"resume\": \"...\",
  \"stade_croissance\": \"...\",
  \"stress_hydrique\": {
    \"niveau\": \"AUCUN | FAIBLE | MODERE | ELEVE | CRITIQUE\",
    \"description\": \"...\"
  },
  \"diagnostic_principal\": {
    \"probleme\": \"...\",
    \"description\": \"...\",
    \"confiance\": 0-100
  },
  \"diagnostics_secondaires\": [
    {
      \"probleme\": \"...\",
      \"description\": \"...\",
      \"confiance\": 0-100
    }
  ],
  \"recommandations\": [
    {
      \"priorite\": \"URGENTE | NORMALE | PREVENTIVE\",
      \"action\": \"...\",
      \"detail\": \"...\"
    }
  ]
}

IMPORTANT:
- Réponds uniquement en JSON
- Aucun texte hors JSON
";
    }

    /**
     * Appel générique à Gemini avec extraction JSON
     */
    public function callGemini(string $prompt): array
    {
        $response = $this->client->request(
            'POST',
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $this->apiKey,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $data = $response->toArray(false);

        // Check for API error responses from Gemini
        if (isset($data['error'])) {
            $errorMsg = $this->buildGeminiErrorMessage($data['error']);
            throw new \Exception($errorMsg);
        }

        if (isset($data['promptFeedback'])) {
            throw new \Exception("Gemini blocked: " . json_encode($data['promptFeedback']));
        }

        if (!isset($data['candidates'][0])) {
            throw new \Exception("Aucune candidate Gemini: " . json_encode($data));
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$text) {
            throw new \Exception("Réponse Gemini vide (parts missing): " . json_encode($data));
        }

        $text = trim($text);
        preg_match('/\{.*\}/s', $text, $matches);

        if (!isset($matches[0])) {
            throw new \Exception("JSON introuvable: " . $text);
        }

        $json = json_decode($matches[0], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON invalide: " . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Transform Gemini API error code into user-friendly French message
     */
    private function buildGeminiErrorMessage(array $error): string
    {
        $code = $error['code'] ?? 0;

        switch ($code) {
            case 429:
                return "Quota IA dépassée. Trop de requêtes. Réessaie dans quelques secondes.";
            case 503:
            case 'UNAVAILABLE':
                return "Service IA temporairement indisponible. Réessaie dans quelques instants.";
            case 401:
            case 403:
                return "Clé API Gemini invalide ou expirée.";
            default:
                return "Erreur IA (code: $code). Réessaie plus tard.";
        }
    }
}
