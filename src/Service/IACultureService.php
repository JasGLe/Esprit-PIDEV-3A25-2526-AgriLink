<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class IACultureService
{
    private HttpClientInterface $client;
    private string $geminiKey;
    private string $groqKey;

    public function __construct(HttpClientInterface $client)
    {
        $this->client    = $client;
        $this->geminiKey = $_ENV['GEMINI_API_KEY'];
        $this->groqKey   = $_ENV['GROQ_API_KEY'];
    }


   // ─────────────────────────────────────────────────────────
    //  RECOMMANDATION CULTURES —  (LLaMA 3.3)
    // ─────────────────────────────────────────────────────────

    public function recommanderCulture(array $parcelle): array
    {
        $response = $this->client->request(
            'POST',
            'https://api.groq.com/openai/v1/chat/completions',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => 'llama-3.3-70b-versatile', 
                    'temperature' => 0.2,                      
                    'max_tokens'  => 1024,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => $this->buildSystemPrompt(),
                        ],
                        [
                            'role'    => 'user',
                            'content' => $this->buildPrompt($parcelle),
                        ],
                    ],
                ],
                'timeout' => 15,
            ]
        );

        $data = $response->toArray(false);

      
        if (isset($data['error'])) {
            throw new \Exception('Groq API error: ' . ($data['error']['message'] ?? json_encode($data['error'])));
        }

        $text = $data['choices'][0]['message']['content'] ?? null;

        if (!$text) {
            throw new \Exception('Réponse Groq vide: ' . json_encode($data));
        }

        return $this->extractJson($text);
    }

    private function buildSystemPrompt(): string
    {
        return "Tu es un expert agronome spécialisé en agriculture tunisienne. 
Tu dois toujours répondre UNIQUEMENT en JSON valide, sans texte avant ni après.
Aucun commentaire, aucune explication hors du JSON.";
    }

    private function buildPrompt(array $p): string
    {
        return "Analyse cette parcelle agricole tunisienne et recommande des cultures adaptées.

DONNÉES PARCELLE:
- Type de sol: {$p['typeSol']}
- État: {$p['etat']}
- Superficie: {$p['superficie']} ha
- Localisation: {$p['ville']}
- Saison actuelle: {$p['saison']}

Réponds UNIQUEMENT avec ce JSON exact (sans markdown, sans texte autour):
{
  \"analyse_sol\": \"description courte du sol et de ses caractéristiques\",
  \"recommandations\": [
    {
      \"nom\": \"Olivier\",
      \"type\": \"OLEICULTURE\",
      \"compatibilite\": 85,
      \"explication\": \"raison courte de la compatibilité\",
      \"saison_ideale\": \"automne\",
      \"duree_mois\": 6
    }
  ],
  \"risques\": \"risques principaux à surveiller pour cette parcelle\"
}

Types autorisés: OLEICULTURE, GRANDES_CULTURES, MARAICHAGE, ARBORICULTURE_FRUITIERE, PHOENICICULTURE, FOURRAGERES
Donne 3  recommandations triées par compatibilité décroissante.";
    }


public function analyserImage($file, string $nomCulture = ''): array
{
    try {
        $imageData = base64_encode(file_get_contents($file->getPathname()));
        $mimeType  = $file->getMimeType();

        $response = $this->client->request(
            'POST',
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=".$this->apiKey,
            [
                'headers' => [
                    'Content-Type' => 'application/json'
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
                                        'data' => $imageData
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
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
                'description' => $e->getMessage()
            ],
            'diagnostic_principal' => [
                'probleme' => 'Erreur IA',
                'description' => $e->getMessage(),
                'confiance' => 0
            ],
            'diagnostics_secondaires' => [],
            'recommandations' => []
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
 * Valide qu'une image correspond bien à la culture indiquée
 */

    public function validerImageCulture(
        string $imageBase64,
        string $mimeType,
        string $nomCulture
    ): array {
        try {
            $prompt = "Analyse image culture {$nomCulture}. Réponds uniquement JSON.";

            $response = $this->client->request(
                'POST',
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=" . $this->geminiKey,
                [
                    'headers' => [
                        'Content-Type' => 'application/json'
                    ],
                    'json' => [
                        'contents' => [[
                            'parts' => [
                                ['text' => $prompt],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data'      => $imageBase64,
                                    ]
                                ]
                            ]
                        ]]
                    ]
                ]
            );

            $data = $response->toArray(false);

            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$text) {
                return ['valide' => false, 'message' => 'Réponse vide', 'confiance' => 0];
            }

            return $this->extractJsonSafe($text);

        } catch (\Throwable $e) {
            return [
                'valide' => false,
                'message' => 'Erreur API: ' . $e->getMessage(),
                'confiance' => 0
            ];
        }
    }


    private function extractJsonSafe(string $text): array
    {
       
        $text = preg_replace('/```json|```/i', '', $text);
        $text = trim($text);

        $start = strpos($text, '{');
        $end   = strrpos($text, '}');

        if ($start === false || $end === false) {
            throw new \Exception("JSON introuvable: " . substr($text, 0, 200));
        }

        $jsonString = substr($text, $start, $end - $start + 1);

        $json = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON invalide: " . json_last_error_msg());
        }

        return $json;
    }
}