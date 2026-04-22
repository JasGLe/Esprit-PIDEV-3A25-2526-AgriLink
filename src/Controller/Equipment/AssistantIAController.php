<?php

namespace App\Controller\Equipment;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * AssistantIAController
 * ─────────────────────────────────────────────────────────────────────────────
 * Assistant IA conversationnel AgriBot — spécialisé équipements & maintenances
 * agricoles. Utilise l'API Groq (LLaMA 4 Scout multimodal) pour répondre aux
 * questions textuelles et analyser des photos d'équipements.
 *
 * Contrairement à DiagnosticController (qui analyse un équipement spécifique
 * depuis la base de données), AssistantIAController est un chat libre :
 * l'utilisateur peut poser des questions générales sur les équipements agricoles,
 * l'entretien, les pannes mécaniques, les pièces de rechange, etc.
 *
 * Capacités :
 *  - Répondre à des questions textuelles sur l'entretien, le diagnostic,
 *    les pièces détachées, la maintenance préventive/corrective.
 *  - Analyser des photos d'équipements (image base64 dans le payload JSON).
 *  - Maintenir un historique de conversation multi-tours (envoi de tous les
 *    messages précédents à chaque requête pour donner le contexte au LLM).
 *  - Refuser poliment toute question hors du domaine agricole/mécanique.
 *
 * Modèle : meta-llama/llama-4-scout-17b-16e-instruct (multimodal)
 * API    : Groq OpenAI-compatible Chat Completions
 *
 * Routes :
 *  GET  /equipement/assistant-ia       → page chat (equipement_assistant_ia)
 *  POST /equipement/assistant-ia/chat  → endpoint AJAX (equipement_assistant_ia_chat)
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AssistantIAController extends AbstractController
{
    /** URL de l'API Groq (format OpenAI Chat Completions). */
    private const GROQ_URL   = 'https://api.groq.com/openai/v1/chat/completions';

    /** Modèle LLaMA 4 Scout — supporte text + images (multimodal). */
    private const GROQ_MODEL = 'meta-llama/llama-4-scout-17b-16e-instruct';

    /**
     * Prompt système : cadre strictement AgriBot sur les équipements agricoles.
     *
     * Ce prompt est injecté en premier dans chaque requête Groq pour :
     *  - Limiter les réponses au domaine agricole/mécanique.
     *  - Assurer une réponse toujours en français.
     *  - Interdire l'invention de pièces ou procédures non vérifiées.
     *  - Activer les capacités d'analyse photo.
     */
    private const SYSTEM_PROMPT = "Tu es AgriBot, un assistant IA spécialisé EXCLUSIVEMENT dans les "
        . "équipements agricoles et leurs maintenances. Tu assistes les agriculteurs "
        . "sur : tracteurs, moissonneuses, pulvérisateurs, irrigations, charrues, "
        . "semoirs, véhicules agricoles, moteurs, pannes mécaniques, entretien, "
        . "diagnostic, pièces de rechange, maintenance préventive et corrective. "
        . "Tu peux analyser des photos d'équipements pour identifier des pannes "
        . "ou l'état de l'équipement. "
        . "Tu REFUSES poliment toute question hors de ce contexte agricole/mécanique "
        . "en disant : 'Je suis AgriBot, spécialisé uniquement en équipements et "
        . "maintenances agricoles. Je ne peux pas répondre à cette question.' "
        . "Tu réponds toujours en français, de manière claire et pratique. "
        . "Tu n'inventes jamais de pièces ou procédures non vérifiées.";

    /**
     * @param HttpClientInterface $httpClient  Client HTTP Symfony pour appeler l'API Groq
     * @param string              $groqApiKey  Clé API Groq (depuis le paramètre Symfony groq_api_key)
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $groqApiKey
    ) {}

    /**
     * Page principale de l'assistant IA.
     *
     * Retourne simplement la vue du chat — toute la logique conversationnelle
     * est côté JavaScript qui appelle l'endpoint /chat en AJAX.
     *
     * @return Response  Vue equipment/assistant_ia/index.html.twig
     */
    #[Route('/equipement/assistant-ia', name: 'equipement_assistant_ia', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('equipment/assistant_ia/index.html.twig');
    }

    /**
     * Endpoint AJAX — reçoit l'historique de conversation et retourne la réponse IA.
     *
     * Payload JSON attendu :
     * {
     *   "messages" : [{"role": "user"|"assistant", "content": "..."}],  // historique complet
     *   "image"    : "base64string...",   // optionnel — si l'utilisateur joint une photo
     *   "mimeType" : "image/jpeg"         // optionnel, requis si image présent
     * }
     *
     * Fonctionnement multi-tour :
     *  1. Décode le payload JSON.
     *  2. Injecte le system prompt en tête des messages Groq.
     *  3. Sépare le dernier message "user" du reste de l'historique.
     *  4. Si une image est jointe, transforme le dernier message en multimodal
     *     (content = [{image_url}, {text}]).
     *  5. Appelle l'API Groq avec l'historique complet pour donner le contexte au LLM.
     *  6. Retourne le texte de réponse du LLM.
     *
     * @param Request $request  Corps JSON avec messages + optionnel image/mimeType
     *
     * @return JsonResponse  {"success": true, "message": "..."} ou {"success": false, "error": "..."}
     */
    #[Route('/equipement/assistant-ia/chat', name: 'equipement_assistant_ia_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        // ── 1. Décoder le payload JSON ─────────────────────────────────────────
        $payload  = json_decode($request->getContent(), true);
        $messages = $payload['messages'] ?? [];
        $image    = $payload['image']    ?? null;
        $mimeType = $payload['mimeType'] ?? null;

        if (empty($messages)) {
            return $this->json(['success' => false, 'error' => 'Aucun message reçu.'], 400);
        }

        // ── 2. Initialiser les messages Groq avec le system prompt ─────────────
        $groqMessages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
        ];

        // ── 3. Séparer le dernier message user de l'historique précédent ───────
        // Le dernier message peut devenir multimodal si une image est jointe
        $lastUserMsg = null;
        $history     = $messages;

        if (!empty($history) && $history[count($history) - 1]['role'] === 'user') {
            $lastUserMsg = array_pop($history);
        }

        // ── 4. Ajouter l'historique précédent (texte uniquement) ───────────────
        foreach ($history as $msg) {
            // Sanitiser le rôle pour éviter d'injecter des rôles non supportés
            $role    = in_array($msg['role'], ['user', 'assistant'], true) ? $msg['role'] : 'user';
            $content = is_string($msg['content']) ? $msg['content'] : '';
            if ($content !== '') {
                $groqMessages[] = ['role' => $role, 'content' => $content];
            }
        }

        // ── 5. Ajouter le dernier message user (multimodal si image jointe) ────
        if ($lastUserMsg !== null) {
            $text = is_string($lastUserMsg['content']) ? $lastUserMsg['content'] : '';

            if ($image && $mimeType && in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                // Message multimodal : image base64 encodée en data URI + texte
                $groqMessages[] = [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'      => 'image_url',
                            'image_url' => ['url' => 'data:' . $mimeType . ';base64,' . $image],
                        ],
                        ['type' => 'text', 'text' => $text ?: 'Analyse cette photo d\'équipement agricole.'],
                    ],
                ];
            } else {
                // Message textuel simple
                $groqMessages[] = ['role' => 'user', 'content' => $text];
            }
        }

        // ── 6. Appel HTTP vers l'API Groq ──────────────────────────────────────
        try {
            $response = $this->httpClient->request('POST', self::GROQ_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => self::GROQ_MODEL,
                    'messages'    => $groqMessages,
                    'max_tokens'  => 1024,
                    'temperature' => 0.7, // Réponses variées mais cohérentes
                ],
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $rawContent = $response->getContent(false); // false = ne pas lever d'exception sur 4xx/5xx

            if ($statusCode !== 200) {
                // Extraire le message d'erreur Groq si possible
                $errData = json_decode($rawContent, true);
                $errMsg  = $errData['error']['message'] ?? $rawContent;
                return $this->json(['success' => false, 'error' => 'Groq API ' . $statusCode . ' : ' . $errMsg], 500);
            }

            $data    = json_decode($rawContent, true);
            $content = $data['choices'][0]['message']['content'] ?? null;

            if ($content === null) {
                return $this->json(['success' => false, 'error' => 'Réponse inattendue de l\'API Groq.'], 500);
            }

            return $this->json(['success' => true, 'message' => $content]);

        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error'   => 'Erreur réseau vers l\'API Groq : ' . $e->getMessage(),
            ], 500);
        }
    }
}
