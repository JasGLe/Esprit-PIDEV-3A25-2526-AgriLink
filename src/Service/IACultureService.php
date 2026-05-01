<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class IACultureService
{
    private const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    private const GROQ_URL   = 'https://api.groq.com/openai/v1/chat/completions';
    private const HF_PLANT_URL = 'https://api-inference.huggingface.co/models/ozair23/mobilenet_v2_1.0_224-finetuned-plantDisease';

    
    private const GROQ_VISION_MODEL = 'meta-llama/llama-4-scout-17b-16e-instruct';
    private const GROQ_TEXT_MODEL   = 'llama-3.3-70b-versatile';

    private string $geminiKey;
    private string $groqKey;
    private string $hfToken;

    public function __construct(private HttpClientInterface $client)
    {
        $this->geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
        $this->groqKey   = $_ENV['GROQ_API_KEY']   ?? '';
        $this->hfToken   = $_ENV['HF_API_TOKEN']   ?? '';
    }

    // ═══════════════════════════════════════════════════════════
    //  RECOMMANDATION CULTURES — Groq LLaMA 3.3 
    // ═══════════════════════════════════════════════════════════

    public function recommanderCulture(array $parcelle): array
    {
        $response = $this->client->request('POST', self::GROQ_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groqKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'       => self::GROQ_TEXT_MODEL,
                'temperature' => 0.2,
                'max_tokens'  => 1024,
                'messages'    => [
                    ['role' => 'system', 'content' => $this->buildSystemPrompt()],
                    ['role' => 'user',   'content' => $this->buildPrompt($parcelle)],
                ],
            ],
            'timeout' => 15,
        ]);

        $data = $response->toArray(false);

        if (isset($data['error'])) {
            throw new \Exception('Groq: ' . ($data['error']['message'] ?? json_encode($data['error'])));
        }

        $text = $data['choices'][0]['message']['content'] ?? '';
        if (!$text) throw new \Exception('Réponse Groq vide');

        return $this->extractJsonSafe($text);
    }

    private function buildSystemPrompt(): string
    {
        return "Tu es un expert agronome spécialisé en agriculture tunisienne. "
             . "Réponds UNIQUEMENT en JSON valide, sans texte avant ni après.";
    }

    private function buildPrompt(array $p): string
    {
        return "Analyse cette parcelle agricole tunisienne et recommande 3 cultures.

DONNÉES:
- Sol: {$p['typeSol']}
- État: {$p['etat']}
- Superficie: {$p['superficie']} ha
- Localisation: {$p['ville']}
- Saison: {$p['saison']}

JSON attendu:
{
  \"analyse_sol\": \"description\",
  \"recommandations\": [
    {
      \"rang\": 1,
      \"nom\": \"Culture\",
      \"type\": \"OLEICULTURE\",
      \"compatibilite\": 85,
      \"explication\": \"raison\",
      \"saison_ideale\": \"automne\",
      \"duree_mois\": 6,
      \"conseils\": \"conseil\",
      \"rendement_estime\": \"3-5 t/ha\"
    }
  ],
  \"risques\": \"risques\"
}

Types: OLEICULTURE, GRANDES_CULTURES, MARAICHAGE, ARBORICULTURE_FRUITIERE, PHOENICICULTURE, FOURRAGERES";
    }

    // ═══════════════════════════════════════════════════════════
    //  ANALYSE IMAGE ANOMALIE — Groq Vision (plante + arbre + animal)
    // ═══════════════════════════════════════════════════════════

    public function analyserImage($file, string $nomSujet = ''): array
    {
        try {
            $imageData = base64_encode(file_get_contents($file->getPathname()));
            $mimeType  = $file->getMimeType();

            // ── Étape 1 : détecter le type de sujet (plante/arbre/animal/autre) ──
            $detection = $this->detecterTypeSujet($imageData, $mimeType);

            if ($detection['type'] === 'autre') {
                return [
                    'image_invalide' => true,
                    'raison'         => $detection['raison'],
                ];
            }

            // ── Étape 2 : analyse spécialisée selon le type ──
            return match ($detection['type']) {
                'plante', 'arbre' => $this->analyserPlantesArbres($imageData, $mimeType, $nomSujet, $detection),
                'animal'          => $this->analyserAnimal($imageData, $mimeType, $nomSujet, $detection),
                default           => ['image_invalide' => true, 'raison' => 'Type non reconnu'],
            };

        } catch (\Throwable $e) {
            return $this->buildErreurAnalyse($e->getMessage());
        }
    }

    // ── Détecte si l'image est une plante, un arbre, un animal, ou autre ──
    private function detecterTypeSujet(string $imageData, string $mimeType): array
    {
        $prompt = "Regarde cette image et identifie le sujet principal.

Réponds UNIQUEMENT avec ce JSON :
{
  \"type\": \"plante\",
  \"sujet_detecte\": \"tomate\",
  \"raison\": \"image montre des plants de tomates\"
}

Valeurs possibles pour 'type' :
- \"plante\"  : toute plante herbacée, légume, céréale, fleur, culture maraîchère
- \"arbre\"   : arbre fruitier, olivier, palmier, figuier, arbre forestier
- \"animal\"  : vache, mouton, chèvre, poulet, cheval, tout animal d'élevage ou sauvage
- \"autre\"   : personne, voiture, bâtiment, objet, dessin, paysage urbain, image abstraite

sujet_detecte = nom précis de ce que tu vois (ex: \"olivier\", \"vache\", \"blé dur\")
raison = description courte en 5-10 mots

IMPORTANT : si c'est 'autre', l'analyse sera refusée.";

        try {
            $text = $this->groqVisionText($imageData, $mimeType, $prompt, 15, 0.0);
            $json = $this->extractJsonSafe($text);

            return [
                'type'           => $json['type']           ?? 'autre',
                'sujet_detecte'  => $json['sujet_detecte']  ?? '',
                'raison'         => $json['raison']         ?? 'Type non reconnu',
            ];
        } catch (\Throwable) {
            // Fail open sur la détection — laisser passer si erreur
            return ['type' => 'plante', 'sujet_detecte' => '', 'raison' => ''];
        }
    }

    // ─────────────────────────────────────────────────────────
    //  ANALYSE PLANTES ET ARBRES
    // ─────────────────────────────────────────────────────────

    private function analyserPlantesArbres(
        string $imageData,
        string $mimeType,
        string $nomSujet,
        array  $detection
    ): array {
        $sujet = $nomSujet ?: $detection['sujet_detecte'] ?: 'plante';

        // Essayer d'abord HuggingFace pour les plantes connues (plus précis)
        if ($this->hfToken && in_array($detection['type'], ['plante'])) {
            $hfResult = $this->analyserAvecHuggingFace($imageData, $mimeType);
            if ($hfResult !== null) {
                return $this->enrichirResultatHF($hfResult, $sujet);
            }
        }

        // Fallback : Groq Vision avec prompt spécialisé plantes/arbres
        $prompt = $this->buildPromptPlantesArbres($sujet, $detection['type']);
        $text   = $this->groqVisionText($imageData, $mimeType, $prompt, 30, 0.1);
        return $this->extractJsonSafe($text);
    }

    private function buildPromptPlantesArbres(string $nomSujet, string $type): string
    {
        $contexte = $type === 'arbre' ? 'arbre/arbuste' : 'plante/culture';
        return "Tu es un expert en phytopathologie et agriculture.

Analyse cette image d'un(e) {$contexte} agricole.
Sujet : {$nomSujet}

Réponds UNIQUEMENT en JSON valide, sans texte autour :
{
  \"type_sujet\": \"plante\",
  \"sujet_identifie\": \"tomate\",
  \"sante_globale\": \"BON\",
  \"score_sante\": 80,
  \"resume\": \"description générale de l'état\",
  \"stade_croissance\": \"végétatif\",
  \"stress_hydrique\": {
    \"niveau\": \"AUCUN\",
    \"description\": \"hydratation normale\"
  },
  \"diagnostic_principal\": {
    \"probleme\": \"Mildiou\",
    \"agent_pathogene\": \"Phytophthora infestans\",
    \"description\": \"taches brunes sur feuilles\",
    \"confiance\": 85
  },
  \"diagnostics_secondaires\": [
    {
      \"probleme\": \"Carence en azote\",
      \"description\": \"jaunissement des vieilles feuilles\",
      \"confiance\": 45
    }
  ],
  \"recommandations\": [
    {
      \"priorite\": \"URGENTE\",
      \"action\": \"Appliquer fongicide\",
      \"detail\": \"Traitement au cuivre dans les 48h\"
    }
  ]
}

Valeurs autorisées :
- sante_globale : BON | MOYEN | MAUVAIS | CRITIQUE
- stress_hydrique.niveau : AUCUN | FAIBLE | MODERE | ELEVE | CRITIQUE
- recommandations.priorite : URGENTE | NORMALE | PREVENTIVE
- Si la plante est saine : diagnostic_principal.probleme = 'Aucune anomalie détectée'";
    }

    // ─────────────────────────────────────────────────────────
    //  ANALYSE ANIMAUX
    // ─────────────────────────────────────────────────────────

    private function analyserAnimal(
        string $imageData,
        string $mimeType,
        string $nomAnimal,
        array  $detection
    ): array {
        $sujet  = $nomAnimal ?: $detection['sujet_detecte'] ?: 'animal';
        $prompt = $this->buildPromptAnimal($sujet);
        $text   = $this->groqVisionText($imageData, $mimeType, $prompt, 30, 0.1);
        return $this->extractJsonSafe($text);
    }

    private function buildPromptAnimal(string $nomAnimal): string
    {
        return "Tu es un vétérinaire expert en élevage et santé animale.

Analyse cette image de l'animal suivant : {$nomAnimal}

Réponds UNIQUEMENT en JSON valide, sans texte autour :
{
  \"type_sujet\": \"animal\",
  \"sujet_identifie\": \"vache\",
  \"sante_globale\": \"BON\",
  \"score_sante\": 80,
  \"resume\": \"description générale de l'état de l'animal\",
  \"stade_croissance\": \"adulte\",
  \"stress_hydrique\": {
    \"niveau\": \"AUCUN\",
    \"description\": \"hydratation normale\"
  },
  \"diagnostic_principal\": {
    \"probleme\": \"Mammite\",
    \"agent_pathogene\": \"Staphylococcus aureus\",
    \"description\": \"inflammation visible du pis\",
    \"confiance\": 80
  },
  \"diagnostics_secondaires\": [
    {
      \"probleme\": \"Boiterie légère\",
      \"description\": \"démarche anormale membre antérieur gauche\",
      \"confiance\": 60
    }
  ],
  \"recommandations\": [
    {
      \"priorite\": \"URGENTE\",
      \"action\": \"Consulter un vétérinaire\",
      \"detail\": \"Traitement antibiotique nécessaire dans les 24h\"
    }
  ]
}

Valeurs autorisées :
- sante_globale : BON | MOYEN | MAUVAIS | CRITIQUE
- stress_hydrique.niveau : AUCUN | FAIBLE | MODERE | ELEVE | CRITIQUE  
- recommandations.priorite : URGENTE | NORMALE | PREVENTIVE
- stade_croissance : jeune | adulte | vieux | inconnu
- Si l'animal est sain : diagnostic_principal.probleme = 'Animal en bonne santé'
- agent_pathogene = 'Aucun' si animal sain";
    }

    // ─────────────────────────────────────────────────────────
    //  HUGGING FACE — Modèle PlantDisease (plantes uniquement)
    // ─────────────────────────────────────────────────────────

    private function analyserAvecHuggingFace(string $imageBase64, string $mimeType): ?array
    {
        try {
            $imageBytes = base64_decode($imageBase64);

            $response = $this->client->request('POST', self::HF_PLANT_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->hfToken,
                    'Content-Type'  => $mimeType,
                ],
                'body'    => $imageBytes,
                'timeout' => 25,
            ]);

            $status = $response->getStatusCode();

            // Modèle en cours de chargement → null (fallback Groq)
            if ($status === 503) return null;

            $predictions = $response->toArray(false);

            if (!is_array($predictions) || empty($predictions) || isset($predictions['error'])) {
                return null;
            }

            return $predictions; // [{"label": "Tomato___Late_blight", "score": 0.95}, ...]

        } catch (\Throwable) {
            return null; // Fallback Groq
        }
    }

    // Enrichir le résultat HuggingFace avec les infos agronomiques
    private function enrichirResultatHF(array $predictions, string $nomSujet): array
    {
        $best      = $predictions[0];
        $label     = $best['label'] ?? '';
        $conf      = round(($best['score'] ?? 0) * 100, 1);
        $healthy   = str_contains(strtolower($label), 'healthy');
        $parts     = explode('___', $label);
        $plantName = str_replace('_', ' ', $parts[0] ?? $nomSujet);
        $disease   = str_replace('_', ' ', $parts[1] ?? '');
        $info      = $this->getDiseaseInfo($label);

        [$score, $sante] = $this->computeHealth($healthy, $best['score'] ?? 0, $info['gravite'] ?? 'MODERE');

        $recommandations = [];
        if (!$healthy) {
            $recommandations[] = [
                'priorite' => ($info['gravite'] ?? '') === 'CRITIQUE' ? 'URGENTE' : 'NORMALE',
                'action'   => $info['traitement'] ?? 'Consulter un agronome',
                'detail'   => 'Agent : ' . ($info['agent'] ?? 'Non identifié'),
            ];
            $recommandations[] = [
                'priorite' => 'PREVENTIVE',
                'action'   => $info['prevention'] ?? 'Surveiller régulièrement',
                'detail'   => 'Mesure préventive recommandée',
            ];
        } else {
            $recommandations[] = [
                'priorite' => 'PREVENTIVE',
                'action'   => 'Maintenir les conditions actuelles',
                'detail'   => 'Culture en bonne santé',
            ];
        }

        // Diagnostics secondaires (top 2-4)
        $secondaires = [];
        foreach (array_slice($predictions, 1, 3) as $p) {
            $i2 = $this->getDiseaseInfo($p['label'] ?? '');
            $secondaires[] = [
                'probleme'    => $i2['nom_fr'] ?? ($p['label'] ?? ''),
                'description' => $p['label'] ?? '',
                'confiance'   => round(($p['score'] ?? 0) * 100, 1),
            ];
        }

        return [
            'type_sujet'       => 'plante',
            'sujet_identifie'  => $plantName,
            'sante_globale'    => $sante,
            'score_sante'      => $score,
            'resume'           => $healthy
                ? "Plante saine — {$plantName}"
                : ($info['nom_fr'] ?? $disease) . " détectée sur {$plantName}",
            'stade_croissance' => 'Non déterminé',
            'stress_hydrique'  => [
                'niveau'      => 'NON_ANALYSE',
                'description' => 'Modèle spécialisé maladies — stress hydrique non analysé',
            ],
            'diagnostic_principal' => [
                'probleme'        => $healthy ? 'Aucune anomalie détectée' : ($info['nom_fr'] ?? $disease),
                'agent_pathogene' => $healthy ? 'Aucun' : ($info['agent'] ?? 'Non identifié'),
                'description'     => "Confiance : {$conf}% — Classe : {$label}",
                'confiance'       => $conf,
            ],
            'diagnostics_secondaires' => $secondaires,
            'recommandations'         => $recommandations,
            'source'                  => 'HuggingFace PlantVillage',
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  BASE DE DONNÉES MALADIES (HuggingFace labels → français)
    // ─────────────────────────────────────────────────────────

    private function getDiseaseInfo(string $label): array
    {
        $db = [
            'Tomato___Late_blight'    => ['nom_fr' => 'Mildiou de la tomate',      'agent' => 'Phytophthora infestans',  'gravite' => 'CRITIQUE', 'traitement' => 'Fongicide systémique (Métalaxyl) en urgence',    'prevention' => 'Rotation cultures, variétés résistantes'],
            'Tomato___Early_blight'   => ['nom_fr' => 'Alternariose tomate',        'agent' => 'Alternaria solani',       'gravite' => 'MODERE',   'traitement' => 'Fongicides Mancozèbe ou Chlorothalonil',          'prevention' => 'Mulching, irrigation au sol'],
            'Tomato___Bacterial_spot' => ['nom_fr' => 'Tache bactérienne tomate',   'agent' => 'Xanthomonas campestris',  'gravite' => 'MODERE',   'traitement' => 'Bouillie bordelaise, cuivre',                     'prevention' => 'Semences certifiées'],
            'Tomato___Leaf_Mold'      => ['nom_fr' => 'Moisissure des feuilles',    'agent' => 'Passalora fulva',         'gravite' => 'FAIBLE',   'traitement' => 'Réduire humidité, fongicides préventifs',         'prevention' => 'Aération, réduire aspersion'],
            'Tomato___Spider_mites Two-spotted_spider_mite' => ['nom_fr' => 'Acariens rouges', 'agent' => 'Tetranychus urticae', 'gravite' => 'MODERE', 'traitement' => 'Acaricides ou savon insecticide', 'prevention' => 'Bonne humidité ambiante'],
            'Tomato___Yellow_Leaf_Curl_Virus' => ['nom_fr' => 'Virus enroulement jaune', 'agent' => 'Begomovirus', 'gravite' => 'CRITIQUE', 'traitement' => 'Arracher plants infectés immédiatement', 'prevention' => 'Contrôle aleurodes, filets anti-insectes'],
            'Tomato___healthy'        => ['nom_fr' => 'Tomate saine',               'agent' => 'Aucun',                   'gravite' => 'AUCUNE',   'traitement' => 'Aucun',                                           'prevention' => 'Bonnes pratiques'],
            'Potato___Late_blight'    => ['nom_fr' => 'Mildiou pomme de terre',     'agent' => 'Phytophthora infestans',  'gravite' => 'CRITIQUE', 'traitement' => 'Fongicides systémiques urgence',                  'prevention' => 'Semences certifiées, buttes hautes'],
            'Potato___Early_blight'   => ['nom_fr' => 'Alternariose pomme de terre','agent' => 'Alternaria solani',       'gravite' => 'MODERE',   'traitement' => 'Fongicides Mancozèbe',                            'prevention' => 'Rotation 3-4 ans'],
            'Potato___healthy'        => ['nom_fr' => 'Pomme de terre saine',       'agent' => 'Aucun',                   'gravite' => 'AUCUNE',   'traitement' => 'Aucun',                                           'prevention' => 'Maintenir irrigation'],
            'Grape___Black_rot'       => ['nom_fr' => 'Pourriture noire vigne',     'agent' => 'Guignardia bidwellii',    'gravite' => 'CRITIQUE', 'traitement' => 'Fongicides Captane avant floraison',              'prevention' => 'Éliminer momies, tailler'],
            'Grape___healthy'         => ['nom_fr' => 'Vigne saine',                'agent' => 'Aucun',                   'gravite' => 'AUCUNE',   'traitement' => 'Aucun',                                           'prevention' => 'Surveiller régulièrement'],
            'Corn_(maize)___Common_rust_'  => ['nom_fr' => 'Rouille commune maïs', 'agent' => 'Puccinia sorghi',         'gravite' => 'MODERE',   'traitement' => 'Fongicides triazoles',                            'prevention' => 'Variétés résistantes'],
            'Corn_(maize)___healthy'  => ['nom_fr' => 'Maïs sain',                 'agent' => 'Aucun',                   'gravite' => 'AUCUNE',   'traitement' => 'Aucun',                                           'prevention' => 'Fertilisation azotée'],
            'Apple___Apple_scab'      => ['nom_fr' => 'Tavelure pommier',           'agent' => 'Venturia inaequalis',     'gravite' => 'MODERE',   'traitement' => 'Fongicides soufre ou triazoles',                  'prevention' => 'Ramassage feuilles, taille'],
            'Apple___healthy'         => ['nom_fr' => 'Pommier sain',               'agent' => 'Aucun',                   'gravite' => 'AUCUNE',   'traitement' => 'Aucun',                                           'prevention' => 'Taille annuelle'],
            'Orange___Haunglongbing_(Citrus_greening)' => ['nom_fr' => 'Huanglongbing', 'agent' => 'Candidatus Liberibacter', 'gravite' => 'CRITIQUE', 'traitement' => 'Arracher et brûler les arbres', 'prevention' => 'Contrôle psylle asiatique'],
        ];

        if (isset($db[$label])) return $db[$label];

        $healthy = str_contains(strtolower($label), 'healthy');
        return [
            'nom_fr'     => $healthy ? 'Plante saine' : str_replace('_', ' ', explode('___', $label)[1] ?? $label),
            'agent'      => $healthy ? 'Aucun' : 'Non identifié',
            'gravite'    => $healthy ? 'AUCUNE' : 'MODERE',
            'traitement' => $healthy ? 'Aucun' : 'Consulter un agronome',
            'prevention' => 'Surveiller régulièrement',
        ];
    }

    private function computeHealth(bool $healthy, float $score, string $gravite): array
    {
        if ($healthy) {
            $s = min(95, 55 + (int)($score * 40));
            return [$s, $s >= 78 ? 'BON' : 'MOYEN'];
        }
        return match ($gravite) {
            'CRITIQUE' => [max(5,  40 - (int)($score * 35)), $score > 0.7 ? 'CRITIQUE' : 'MAUVAIS'],
            'MODERE'   => [max(25, 65 - (int)($score * 35)), 'MAUVAIS'],
            default    => [max(40, 75 - (int)($score * 25)), 'MOYEN'],
        };
    }

    // ═══════════════════════════════════════════════════════════
    //  VALIDATION IMAGE / CULTURE (inchangé, utilise Gemini)
    // ═══════════════════════════════════════════════════════════

    public function validerImageCulture(
        string $imageBase64,
        string $mimeType,
        string $culture
    ): array {
        $prompt = "Tu es un botaniste expert.

Culture attendue : \"{$culture}\"

Réponds UNIQUEMENT avec ce JSON :
{
  \"culture_detectee\": \"nom exact\",
  \"valide\": true,
  \"confiance\": 90,
  \"message\": \"raison courte\"
}

RÈGLES :
- valide = true si l'image montre \"{$culture}\" ou variété proche
- valide = false si image montre autre chose (même si agricole)
- valide = false si dessin, œuvre d'art, objet, personne, animal
- Image floue/ambiguë → valide = true, confiance < 50";

        try {
            $text = $this->geminiVisionText($imageBase64, $mimeType, $prompt, 20, 0.0);
            $json = $this->extractJsonSafe($text);

            $confiance = (int)($json['confiance'] ?? 70);

            // Image trop ambiguë → accepter
            if ($confiance < 40) {
                return ['valide' => true, 'confiance' => $confiance, 'culture_detectee' => $culture, 'message' => 'Image ambiguë acceptée'];
            }

            return [
                'valide'           => (bool)($json['valide'] ?? true),
                'confiance'        => $confiance,
                'culture_detectee' => (string)($json['culture_detectee'] ?? ''),
                'message'          => (string)($json['message'] ?? ''),
            ];

        } catch (\Throwable $e) {
            return ['valide' => true, 'confiance' => 0, 'culture_detectee' => '', 'message' => 'Validation non disponible'];
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  HELPERS HTTP
    // ═══════════════════════════════════════════════════════════

    // Appel Groq Vision (texte + image)
    private function groqVisionText(
        string $imageBase64,
        string $mimeType,
        string $prompt,
        int    $timeout = 30,
        float  $temperature = 0.1
    ): string {
        $response = $this->client->request('POST', self::GROQ_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groqKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'       => self::GROQ_VISION_MODEL,
                'temperature' => $temperature,
                'max_tokens'  => 600,
                'messages'    => [[
                    'role'    => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                        [
                            'type'      => 'image_url',
                            'image_url' => [
                                'url' => "data:{$mimeType};base64,{$imageBase64}",
                            ],
                        ],
                    ],
                ]],
            ],
            'timeout' => $timeout,
        ]);

        $data = $response->toArray(false);

        if (isset($data['error'])) {
            throw new \RuntimeException('Groq Vision error: ' . ($data['error']['message'] ?? json_encode($data['error'])));
        }

        $text = $data['choices'][0]['message']['content'] ?? null;
        if (!$text) throw new \RuntimeException('Réponse Groq Vision vide');

        return $text;
    }

    // Appel Gemini Vision (utilisé seulement pour validerImageCulture)
    private function geminiVisionText(
        string $imageBase64,
        string $mimeType,
        string $prompt,
        int    $timeout = 20,
        float  $temperature = 0.1
    ): string {
        $response = $this->client->request('POST', self::GEMINI_URL . '?key=' . $this->geminiKey, [
            'headers' => ['Content-Type' => 'application/json'],
            'json'    => [
                'contents' => [[
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $imageBase64]],
                    ],
                ]],
                'generationConfig' => ['temperature' => $temperature, 'maxOutputTokens' => 200],
            ],
            'timeout' => $timeout,
        ]);

        $data = $response->toArray(false);

        if (isset($data['error'])) {
            throw new \RuntimeException('Gemini error: ' . ($data['error']['message'] ?? json_encode($data['error'])));
        }

        if (isset($data['promptFeedback']['blockReason'])) {
            throw new \RuntimeException('Gemini bloqué: ' . $data['promptFeedback']['blockReason']);
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$text) throw new \RuntimeException('Réponse Gemini vide');

        return $text;
    }

    // ═══════════════════════════════════════════════════════════
    //  PARSER JSON
    // ═══════════════════════════════════════════════════════════

    private function extractJsonSafe(string $text): array
    {
        $text  = preg_replace('/```json\s*/i', '', $text);
        $text  = preg_replace('/```\s*/', '', $text);
        $text  = trim($text);
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            return ['valide' => true, 'confiance' => 70, 'culture_detectee' => '', 'message' => 'Réponse acceptée'];
        }

        $json = json_decode(substr($text, $start, $end - $start + 1), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['valide' => true, 'confiance' => 70, 'culture_detectee' => '', 'message' => 'Réponse acceptée'];
        }

        return $json;
    }

    private function buildErreurAnalyse(string $message): array
    {
        return [
            'type_sujet'              => 'inconnu',
            'sujet_identifie'         => 'Inconnu',
            'sante_globale'           => 'MOYEN',
            'score_sante'             => 50,
            'resume'                  => 'Erreur analyse IA',
            'stade_croissance'        => 'Inconnu',
            'stress_hydrique'         => ['niveau' => 'INCONNU', 'description' => $message],
            'diagnostic_principal'    => ['probleme' => 'Erreur IA', 'agent_pathogene' => '', 'description' => $message, 'confiance' => 0],
            'diagnostics_secondaires' => [],
            'recommandations'         => [['priorite' => 'NORMALE', 'action' => 'Réessayer', 'detail' => $message]],
        ];
    }
}