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
 * agricoles. Utilise Groq (LLaMA 4 Scout multimodal) pour répondre aux
 * questions textuelles et analyser des photos d'équipements.
 *
 * Routes :
 *  GET  /equipement/assistant-ia       → page chat (equipement_assistant_ia)
 *  POST /equipement/assistant-ia/chat  → endpoint AJAX (equipement_assistant_ia_chat)
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AssistantIAController extends AbstractController
{
    private const GROQ_URL   = 'https://api.groq.com/openai/v1/chat/completions';
    private const GROQ_MODEL = 'meta-llama/llama-4-scout-17b-16e-instruct';

    /**
     * System prompt : cadre strictement AgriBot sur les équipements agricoles.
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

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $groqApiKey
    ) {}

    /**
     * Page principale de l'assistant IA.
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
     *   "messages"  : [{role: "user"|"assistant", content: "..."}],  // historique complet
     *   "image"     : "base64string...",  // optionnel
     *   "mimeType"  : "image/jpeg"        // optionnel, requis si image présent
     * }
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

        // ── 2. Construire les messages Groq ────────────────────────────────────
        $groqMessages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
        ];

        // Séparer le dernier message user (peut devenir multimodal si image)
        $lastUserMsg = null;
        $history     = $messages;

        if (!empty($history) && $history[count($history) - 1]['role'] === 'user') {
            $lastUserMsg = array_pop($history);
        }

        // Ajouter l'historique précédent (sans le dernier user)
        foreach ($history as $msg) {
            $role    = in_array($msg['role'], ['user', 'assistant'], true) ? $msg['role'] : 'user';
            $content = is_string($msg['content']) ? $msg['content'] : '';
            if ($content !== '') {
                $groqMessages[] = ['role' => $role, 'content' => $content];
            }
        }

        // ── 3. Ajouter le dernier message user (multimodal si image) ──────────
        if ($lastUserMsg !== null) {
            $text = is_string($lastUserMsg['content']) ? $lastUserMsg['content'] : '';

            if ($image && $mimeType && in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                // Message multimodal : image base64 + texte
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
                $groqMessages[] = ['role' => 'user', 'content' => $text];
            }
        }

        // ── 4. Appel HTTP vers l'API Groq ──────────────────────────────────────
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
                    'temperature' => 0.7,
                ],
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $rawContent = $response->getContent(false);

            if ($statusCode !== 200) {
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
