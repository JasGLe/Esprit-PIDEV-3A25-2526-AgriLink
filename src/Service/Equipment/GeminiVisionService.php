<?php

namespace App\Service\Equipment;

use App\Entity\Equipement;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * GeminiVisionService
 * ─────────────────────────────────────────────────────────────────────────────
 * Analyse visuelle d'un équipement agricole via l'API Groq multimodale.
 *
 * Malgré son nom (héritage historique du projet), ce service utilise
 * Groq (LLaMA 4 Scout 17B multimodal) et NON Gemini de Google.
 * Il est nommé "GeminiVision" par convention dans le projet.
 *
 * Fonctionnement :
 *  1. Reçoit une image encodée en base64 + son mimeType + l'entité Equipement
 *  2. Construit une requête multimodale OpenAI-compatible :
 *     - Message système : cadre l'analyse sur le diagnostic visuel agricole
 *     - Message utilisateur : image (data URI base64) + prompt textuel enrichi
 *       des données de l'équipement (nom, type, marque, statut déclaré)
 *  3. Envoie la requête HTTP POST vers l'API Groq avec timeout 30s
 *  4. Nettoie les éventuels backticks markdown du LLM
 *  5. Parse et normalise la réponse JSON structurée
 *
 * Format de réponse normalisé :
 * {
 *   "conformite" : {
 *     "statut"               : "conforme|non_conforme_agricole|non_agricole",
 *     "equipement_detecte"   : "description de ce qui est visible",
 *     "equipement_attendu"   : "nom - type - categorie (pré-rempli depuis l'entité)",
 *     "message_conformite"   : "explication courte",
 *     "continuer_diagnostic" : true|false
 *   },
 *   "etat_visuel"               : "Bon|Usure normale|Dégradation|Critique",
 *   "score_visuel"              : 0-100,
 *   "anomalies_detectees"       : ["...", "..."],
 *   "zones_problematiques"      : ["...", "..."],
 *   "recommandations_visuelles" : ["...", "..."],
 *   "conclusion"                : "..."
 * }
 *
 * Paramétrage :
 *  - GROQ_API_KEY : clé API (env var, injectée via services.yaml)
 *  - Modèle       : meta-llama/llama-4-scout-17b-16e-instruct (hardcodé)
 *  - temperature=0.3 : réponses précises et reproductibles
 * ─────────────────────────────────────────────────────────────────────────────
 */
class GeminiVisionService
{
    /** URL de l'API Groq (format OpenAI Chat Completions). */
    private const GROQ_URL   = 'https://api.groq.com/openai/v1/chat/completions';

    /**
     * Modèle multimodal LLaMA 4 Scout — supporte images + texte.
     * Hardcodé car c'est le seul modèle Groq supportant les images actuellement.
     */
    private const GROQ_MODEL = 'meta-llama/llama-4-scout-17b-16e-instruct';

    /**
     * Prompt système : limite le LLM au diagnostic visuel agricole.
     * Exige une réponse JSON pur (sans markdown, sans texte autour).
     */
    private const SYSTEM_PROMPT = 'Tu es un expert en diagnostic visuel d\'équipements agricoles. '
        . 'Analyse UNIQUEMENT les signes visuels de dégradation, usure, corrosion, dommages mécaniques '
        . 'ou anomalies sur cet équipement agricole. '
        . 'Ne réponds à rien d\'autre qu\'un diagnostic visuel d\'équipement agricole. '
        . 'Réponds UNIQUEMENT en JSON valide, sans texte avant ni après, sans balises markdown.';

    /**
     * @param HttpClientInterface $httpClient  Client HTTP Symfony pour appeler l'API Groq
     * @param string              $groqApiKey  Clé API Groq (depuis paramètre Symfony groq_api_key)
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $groqApiKey
    ) {}

    /**
     * Analyse visuellement une photo d'équipement agricole.
     *
     * L'image est transmise au LLM en base64 via une data URI (data:image/jpeg;base64,...).
     * Le prompt textuel inclut les données de l'équipement pour permettre au LLM
     * de contextualiser son analyse (ex: "tracteur John Deere, statut En panne"
     * orientera l'analyse vers les signes typiques de panne de tracteur).
     *
     * Normalisation de la réponse :
     * Si le LLM omet des champs, des valeurs par défaut sont appliquées :
     *  - etat_visuel : "Inconnu"
     *  - score_visuel : 50
     *  - anomalies_detectees : []
     *  - zones_problematiques : []
     *  - recommandations_visuelles : []
     *  - conclusion : ""
     *
     * @param string     $base64Image  Image encodée en base64 (sans préfixe data URI)
     * @param string     $mimeType     Type MIME : image/jpeg, image/png ou image/webp
     * @param Equipement $equipement   Entité pour enrichir le prompt (nom, type, marque, statut)
     *
     * @return array  Résultat normalisé avec conformite (statut, equipement_detecte,
     *                equipement_attendu, message_conformite, continuer_diagnostic),
     *                etat_visuel, score_visuel, anomalies, zones, recommandations et conclusion
     *
     * @throws \RuntimeException  Si l'API retourne une erreur HTTP, une réponse vide ou un JSON invalide
     */
    public function analyserPhoto(string $base64Image, string $mimeType, Equipement $equipement): array
    {
        // ── Prompt utilisateur : conformité + diagnostic visuel ──────────────
        // Étape 1 : vérifier si l'image correspond à l'équipement attendu.
        // Étape 2 : effectuer le diagnostic visuel complet quelle que soit l'image.
        $nomStr     = $equipement->getNom()       ?? 'Non renseigné';
        $typeStr    = $equipement->getType()      ?? 'Non renseigné';
        $catStr     = $equipement->getCategorie() ?? 'Non renseigné';
        $marqueStr  = $equipement->getMarque()    ?? 'Non renseigné';
        $statutStr  = $equipement->getStatut()    ?? 'Non renseigné';
        $attenduStr = $nomStr . ' - ' . $typeStr . ' - ' . $catStr;

        $userPrompt = "Analyse cette image dans le contexte suivant :\n"
            . "Équipement attendu : {$nomStr} (Type: {$typeStr}, Catégorie: {$catStr}, "
            . "Marque: {$marqueStr}, Statut déclaré: {$statutStr}).\n\n"
            . "ÉTAPE 1 — VÉRIFICATION DE CONFORMITÉ :\n"
            . "Identifie ce que tu vois sur l'image.\n"
            . "Détermine si l'image correspond à cet équipement spécifique.\n"
            . "Classe en 3 cas :\n"
            . "- 'conforme' : l'image correspond exactement à cet équipement\n"
            . "- 'non_conforme_agricole' : l'image montre un autre équipement agricole "
            . "(pas celui attendu mais reste dans le domaine agricole)\n"
            . "- 'non_agricole' : l'image ne montre pas un équipement agricole\n\n"
            . "ÉTAPE 2 — DIAGNOSTIC VISUEL :\n"
            . "Effectue le diagnostic visuel complet de ce qui est visible sur l'image "
            . "(anomalies, zones problématiques, recommandations) même si l'image n'est pas l'équipement attendu.\n\n"
            . "Règle pour continuer_diagnostic :\n"
            . "- conforme → continuer_diagnostic: true\n"
            . "- non_conforme_agricole → continuer_diagnostic: false\n"
            . "- non_agricole → continuer_diagnostic: false\n\n"
            . "Réponds UNIQUEMENT en JSON valide sans markdown :\n"
            . '{"conformite":{"statut":"conforme|non_conforme_agricole|non_agricole",'
            . '"equipement_detecte":"description de ce que tu vois",'
            . '"equipement_attendu":"' . $attenduStr . '",'
            . '"message_conformite":"explication courte","continuer_diagnostic":true},'
            . '"etat_visuel":"Bon|Usure normale|Dégradation|Critique","score_visuel":0,'
            . '"anomalies_detectees":[],"zones_problematiques":[],'
            . '"recommandations_visuelles":[],"conclusion":""}';


        // ── Corps de la requête Groq (format OpenAI multimodal image_url) ────
        $requestBody = [
            'model'       => self::GROQ_MODEL,
            'messages'    => [
                // Message système : cadre le comportement du LLM
                [
                    'role'    => 'system',
                    'content' => self::SYSTEM_PROMPT,
                ],
                // Message utilisateur : image en data URI + texte descriptif
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'      => 'image_url',
                            'image_url' => [
                                // Format data URI : data:image/jpeg;base64,{base64}
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
            'max_tokens'  => 1500,
            'temperature' => 0.3, // réponses précises et stables
        ];

        // ── Appel HTTP vers l'API Groq ────────────────────────────────────────
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
            $rawContent = $response->getContent(false); // false = ne pas lever d'exception sur 4xx

            if ($statusCode !== 200) {
                $errData = json_decode($rawContent, true);
                $errMsg  = $errData['error']['message'] ?? $rawContent;
                throw new \RuntimeException('Groq Vision API ' . $statusCode . ' : ' . $errMsg);
            }

            $data = json_decode($rawContent, true);

        } catch (\RuntimeException $e) {
            // Propager les erreurs Groq explicites vers le contrôleur
            throw $e;
        } catch (\Throwable $e) {
            // Erreur réseau / transport Symfony HttpClient
            throw new \RuntimeException('Erreur réseau vers l\'API Groq Vision : ' . $e->getMessage());
        }

        // ── Extraire le contenu texte de la réponse (format OpenAI) ──────────
        $content = $data['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            throw new \RuntimeException('Réponse inattendue de l\'API Groq Vision (pas de contenu).');
        }

        // ── Nettoyer les éventuels backticks markdown du LLM ─────────────────
        // Malgré la consigne, le LLM peut parfois entourer le JSON de ```json ... ```
        $content = preg_replace('/^```json\s*/i', '', trim($content));
        $content = preg_replace('/```\s*$/', '', $content);
        $content = trim($content);

        $analyse = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('La réponse Groq Vision n\'est pas un JSON valide : ' . $content);
        }

        // ── Normaliser les champs avec des valeurs par défaut ─────────────────
        // Garantit que le tableau retourné a toujours tous les champs attendus,
        // même si le LLM en omet un (robustesse côté template Twig / JS)
        $conformiteRaw = is_array($analyse['conformite'] ?? null) ? $analyse['conformite'] : [];

        return [
            'conformite' => [
                'statut'               => $conformiteRaw['statut']               ?? 'conforme',
                'equipement_detecte'   => $conformiteRaw['equipement_detecte']   ?? '',
                'equipement_attendu'   => $conformiteRaw['equipement_attendu']   ?? '',
                'message_conformite'   => $conformiteRaw['message_conformite']   ?? '',
                'continuer_diagnostic' => $conformiteRaw['continuer_diagnostic'] ?? true,
            ],
            'etat_visuel'               => $analyse['etat_visuel']               ?? 'Inconnu',
            'score_visuel'              => (int) ($analyse['score_visuel']        ?? 50),
            'anomalies_detectees'       => (array) ($analyse['anomalies_detectees']       ?? []),
            'zones_problematiques'      => (array) ($analyse['zones_problematiques']      ?? []),
            'recommandations_visuelles' => (array) ($analyse['recommandations_visuelles'] ?? []),
            'conclusion'                => $analyse['conclusion'] ?? '',
        ];
    }
}
