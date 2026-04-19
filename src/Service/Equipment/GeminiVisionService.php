<?php

namespace App\Service\Equipment;

use App\Entity\Equipement;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * GeminiVisionService
 * ─────────────────────────────────────────────────────────────────────────────
 * Analyse visuelle d'un équipement agricole via Groq (LLaMA 4 Scout multimodal).
 *
 * Fonctionnement :
 *  1. Reçoit une image encodée en base64 + son mimeType + l'entité Equipement
 *  2. Construit une requête multimodale OpenAI-compatible (image_url + texte)
 *  3. Envoie la requête HTTP POST vers l'API Groq
 *  4. Parse et retourne le JSON structuré de l'analyse visuelle
 * ─────────────────────────────────────────────────────────────────────────────
 */
class GeminiVisionService
{
    private const GROQ_URL   = 'https://api.groq.com/openai/v1/chat/completions';
    private const GROQ_MODEL = 'meta-llama/llama-4-scout-17b-16e-instruct';

    /**
     * Prompt système : interdit toute réponse hors diagnostic visuel agricole.
     */
    private const SYSTEM_PROMPT = 'Tu es un expert en diagnostic visuel d\'équipements agricoles. '
        . 'Analyse UNIQUEMENT les signes visuels de dégradation, usure, corrosion, dommages mécaniques '
        . 'ou anomalies sur cet équipement agricole. '
        . 'Ne réponds à rien d\'autre qu\'un diagnostic visuel d\'équipement agricole. '
        . 'Réponds UNIQUEMENT en JSON valide, sans texte avant ni après, sans balises markdown.';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $groqApiKey
    ) {}

    /**
     * Analyse visuellement une photo d'équipement agricole.
     *
     * @param string      $base64Image  Image encodée en base64 (sans préfixe data URI)
     * @param string      $mimeType     Type MIME de l'image (image/jpeg, image/png, image/webp)
     * @param Equipement  $equipement   Entité équipement pour enrichir le prompt
     *
     * @return array  Résultat structuré : etat_visuel, score_visuel, anomalies_detectees, etc.
     * @throws \RuntimeException  En cas d'erreur HTTP ou de réponse non-JSON
     */
    public function analyserPhoto(string $base64Image, string $mimeType, Equipement $equipement): array
    {
        // ── Prompt utilisateur enrichi des données de l'équipement ────────────
        $userPrompt = sprintf(
            "Analyse cette photo de l'équipement agricole :\n"
            . "Nom: %s, Type: %s, Catégorie: %s, Marque: %s, Statut déclaré: %s.\n"
            . "Détecte tous les signes visuels de problèmes et réponds en JSON.\n\n"
            . "Réponds UNIQUEMENT avec ce JSON valide (sans markdown, sans texte autour) :\n"
            . '{"etat_visuel":"Bon|Usure normale|Dégradation|Critique","score_visuel":0,'
            . '"anomalies_detectees":[],"zones_problematiques":[],'
            . '"recommandations_visuelles":[],"conclusion":""}',
            $equipement->getNom()       ?? 'Non renseigné',
            $equipement->getType()      ?? 'Non renseigné',
            $equipement->getCategorie() ?? 'Non renseigné',
            $equipement->getMarque()    ?? 'Non renseigné',
            $equipement->getStatut()    ?? 'Non renseigné'
        );

        // ── Corps de la requête Groq (format OpenAI — multimodal image_url) ───
        $requestBody = [
            'model'       => self::GROQ_MODEL,
            'messages'    => [
                // Message système : cadre le comportement du LLM
                [
                    'role'    => 'system',
                    'content' => self::SYSTEM_PROMPT,
                ],
                // Message utilisateur : image + texte
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'      => 'image_url',
                            'image_url' => [
                                'url' => 'data:' . $mimeType . ';base64,' . $base64Image,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $userPrompt,
                        ],
                    ],
                ],
            ],
            'max_tokens'  => 1024,
            'temperature' => 0.3,
        ];

        // ── Appel HTTP ────────────────────────────────────────────────────────
        try {
            $response = $this->httpClient->request('POST', self::GROQ_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json'    => $requestBody,
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $rawContent = $response->getContent(false);

            if ($statusCode !== 200) {
                $errData = json_decode($rawContent, true);
                $errMsg  = $errData['error']['message'] ?? $rawContent;
                throw new \RuntimeException('Groq Vision API ' . $statusCode . ' : ' . $errMsg);
            }

            $data = json_decode($rawContent, true);

        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Erreur réseau vers l\'API Groq Vision : ' . $e->getMessage());
        }

        // ── Extraire le contenu texte de la réponse (format OpenAI) ──────────
        $content = $data['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            throw new \RuntimeException('Réponse inattendue de l\'API Groq Vision (pas de contenu).');
        }

        // ── Nettoyer les éventuels backticks markdown du LLM ──────────────────
        $content = preg_replace('/^```json\s*/i', '', trim($content));
        $content = preg_replace('/```\s*$/', '', $content);
        $content = trim($content);

        $analyse = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('La réponse Groq Vision n\'est pas un JSON valide : ' . $content);
        }

        // ── Normaliser les champs attendus avec des valeurs par défaut ─────────
        return [
            'etat_visuel'               => $analyse['etat_visuel']               ?? 'Inconnu',
            'score_visuel'              => (int) ($analyse['score_visuel']        ?? 50),
            'anomalies_detectees'       => (array) ($analyse['anomalies_detectees']       ?? []),
            'zones_problematiques'      => (array) ($analyse['zones_problematiques']      ?? []),
            'recommandations_visuelles' => (array) ($analyse['recommandations_visuelles'] ?? []),
            'conclusion'                => $analyse['conclusion'] ?? '',
        ];
    }
}
