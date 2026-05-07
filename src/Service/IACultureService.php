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

    /**
     * @param array<string, mixed> $parcelle
     * @return array<string, mixed>
     */
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

    /**
     * @param array<string, mixed> $p
     */
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

    /**
     * @param \Symfony\Component\HttpFoundation\File\UploadedFile $file
     * @return array<string, mixed>
     */
    public function analyserImage($file, string $nomSujet = ''): array
    {
        try {
            $imageData = base64_encode((string) file_get_contents($file->getPathname()));
            $mimeType  = $file->getMimeType() ?? 'image/jpeg';

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
    /**
     * @return array{type: string, sujet_detecte: string, raison: string}
     */
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

    /**
     * @param array{type: string, sujet_detecte: string, raison: string} $detection
     * @return array<string, mixed>
     */
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

    /**
     * @param array{type: string, sujet_detecte: string, raison: string} $detection
     * @return array<string, mixed>
     */
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

    /**
     * @return array<int, array{label: string, score: float}>|null
     */
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

            if (empty($predictions) || isset($predictions['error'])) {
                return null;
            }

            return $predictions; // [{"label": "Tomato___Late_blight", "score": 0.95}, ...]

        } catch (\Throwable) {
            return null; // Fallback Groq
        }
    }

    /**
     * @param array<int, array{label: string, score: float}> $predictions
     * @return array<string, mixed>
     */
    private function enrichirResultatHF(array $predictions, string $nomSujet): array
    {
        $best      = $predictions[0];
        $label     = $best['label'];
        $conf      = round($best['score'] * 100, 1);
        $healthy   = str_contains(strtolower($label), 'healthy');
        $parts     = explode('___', $label);
        $plantName = str_replace('_', ' ', $parts[0] !== '' ? $parts[0] : $nomSujet);
        $disease   = str_replace('_', ' ', $parts[1] ?? '');
        $info      = $this->getDiseaseInfo($label);

        [$score, $sante] = $this->computeHealth($healthy, $best['score'], $info['gravite']);

        $recommandations = [];
        if (!$healthy) {
            $recommandations[] = [
                'priorite' => $info['gravite'] === 'CRITIQUE' ? 'URGENTE' : 'NORMALE',
                'action'   => $info['traitement'],
                'detail'   => 'Agent : ' . $info['agent'],
            ];
            $recommandations[] = [
                'priorite' => 'PREVENTIVE',
                'action'   => $info['prevention'],
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
            $i2 = $this->getDiseaseInfo($p['label']);
            $secondaires[] = [
                'probleme'    => $i2['nom_fr'],
                'description' => $p['label'],
                'confiance'   => round($p['score'] * 100, 1),
            ];
        }

        return [
            'type_sujet'       => 'plante',
            'sujet_identifie'  => $plantName,
            'sante_globale'    => $sante,
            'score_sante'      => $score,
            'resume'           => $healthy
                ? "Plante saine — {$plantName}"
                : $info['nom_fr'] . " détectée sur {$plantName}",
            'stade_croissance' => 'Non déterminé',
            'stress_hydrique'  => [
                'niveau'      => 'NON_ANALYSE',
                'description' => 'Modèle spécialisé maladies — stress hydrique non analysé',
            ],
            'diagnostic_principal' => [
                'probleme'        => $healthy ? 'Aucune anomalie détectée' : $info['nom_fr'],
                'agent_pathogene' => $healthy ? 'Aucun' : $info['agent'],
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

    /**
     * @return array{nom_fr: string, agent: string, gravite: string, traitement: string, prevention: string}
     */
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
        $parts2  = explode('___', $label);
        return [
            'nom_fr'     => $healthy ? 'Plante saine' : str_replace('_', ' ', $parts2[1] ?? $label),
            'agent'      => $healthy ? 'Aucun' : 'Non identifié',
            'gravite'    => $healthy ? 'AUCUNE' : 'MODERE',
            'traitement' => $healthy ? 'Aucun' : 'Consulter un agronome',
            'prevention' => 'Surveiller régulièrement',
        ];
    }

    /**
     * @return array{0: int, 1: string}
     */
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
    //  VALIDATION IMAGE / CULTURE — Groq Vision + comparaison stricte
    // ═══════════════════════════════════════════════════════════

    /**
     * @return array{valide: bool, confiance: int, culture_detectee: string, message: string}
     */
    public function validerImageCulture(
        string $imageBase64,
        string $mimeType,
        string $culture
    ): array {
        // ── Étape 1 : identification visuelle pure via Groq Vision ──
        // On NE mentionne PAS la culture attendue pour éviter tout biais.
        $promptId = <<<PROMPT
You are a botanical expert. Look at this image and identify the plant.

Respond ONLY with this JSON (no text before or after):
{
  "plante_identifiee": "exact plant name in French",
  "plante_en": "exact plant name in English",
  "confiance": 85,
  "caracteristiques_visuelles": "brief visual description"
}

Rules:
- "plante_identifiee" must be the plant you actually SEE in the image
- Examples: "tomate", "olivier", "pomme de terre", "blé", "maïs", "vigne"
- If NOT a plant at all (person, car, building, drawing) → plante_identifiee = "non-plante", plante_en = "non-plant"
- If image is blurry/unreadable → plante_identifiee = "illisible", confiance = 5
- confiance = your certainty 0-100
- Do NOT be influenced by what you think the user wants — identify only what is visually present
PROMPT;

        try {
            // Utiliser Groq Vision (plus fiable que Gemini pour l'identification visuelle)
            $textId = $this->groqVisionText($imageBase64, $mimeType, $promptId, 25, 0.0);
            $jsonId = $this->extractJsonSafe($textId);

            $planteDetectee = strtolower(trim((string)($jsonId['plante_identifiee'] ?? '')));
            $planteEn       = strtolower(trim((string)($jsonId['plante_en']         ?? '')));
            $confiance      = (int)($jsonId['confiance'] ?? 50);
            $caracVisuels   = (string)($jsonId['caracteristiques_visuelles'] ?? '');

            // Déduire est_plante depuis le nom détecté, pas depuis le champ booléen
            // (le champ booléen peut être absent ou mal parsé)
            $estNonPlante = ($planteDetectee === 'non-plante' || $planteDetectee === '')
                         && isset($jsonId['est_plante']) && $jsonId['est_plante'] === false;

            // Image illisible → accepter sans bloquer
            if ($planteDetectee === 'illisible' || $confiance < 20) {
                return [
                    'valide'           => true,
                    'confiance'        => $confiance,
                    'culture_detectee' => $culture,
                    'message'          => 'Image ambiguë — validation impossible',
                ];
            }

            // Pas une plante → rejeter seulement si le nom détecté est explicitement "non-plante"
            if ($estNonPlante) {
                return [
                    'valide'           => false,
                    'confiance'        => $confiance,
                    'culture_detectee' => (string)($jsonId['plante_identifiee'] ?? 'non-plante'),
                    'message'          => 'L\'image ne montre pas une plante agricole.',
                ];
            }

            // Si le nom détecté est vide (parsing raté) → accepter sans bloquer
            if ($planteDetectee === '') {
                return [
                    'valide'           => true,
                    'confiance'        => 0,
                    'culture_detectee' => $culture,
                    'message'          => 'Validation non disponible',
                ];
            }

            // ── Étape 2 : comparaison PHP stricte ──
            $cultureNorm = strtolower(trim($culture));
            $match = $this->nomsCultureCorrespondent($cultureNorm, $planteDetectee)
                  || $this->nomsCultureCorrespondent($cultureNorm, $planteEn);

            if ($match) {
                return [
                    'valide'           => true,
                    'confiance'        => $confiance,
                    'culture_detectee' => (string)($jsonId['plante_identifiee'] ?? $culture),
                    'message'          => $caracVisuels ?: 'Image validée',
                ];
            }

            // ── Étape 3 : si confiance < 50, demander confirmation à Groq ──
            // (cas où l'identification est incertaine — ex: jeune pousse difficile à distinguer)
            if ($confiance < 50) {
                $confirm = $this->confirmerCorrespondance($imageBase64, $mimeType, $culture, $planteDetectee);
                if ($confirm) {
                    return [
                        'valide'           => true,
                        'confiance'        => $confiance,
                        'culture_detectee' => $culture,
                        'message'          => 'Image acceptée (identification incertaine)',
                    ];
                }
            }

            // Noms différents → rejeter
            return [
                'valide'           => false,
                'confiance'        => $confiance,
                'culture_detectee' => (string)($jsonId['plante_identifiee'] ?? $planteDetectee),
                'message'          => sprintf(
                    'Image refusée : l\'IA a identifié « %s » mais vous avez saisi « %s ».',
                    $jsonId['plante_identifiee'] ?? $planteDetectee,
                    $culture
                ),
            ];

        } catch (\Throwable $e) {
            // Erreur API → accepter pour ne pas bloquer
            return ['valide' => true, 'confiance' => 0, 'culture_detectee' => '', 'message' => 'Validation non disponible'];
        }
    }

    /**
     * Confirmation binaire : est-ce que l'image montre bien $culture ?
     * Utilisé uniquement quand la confiance d'identification est faible.
     */
    private function confirmerCorrespondance(
        string $imageBase64,
        string $mimeType,
        string $culture,
        string $detecte
    ): bool {
        $prompt = <<<PROMPT
Look at this image carefully.

Does this image show "{$culture}" (also known as "{$detecte}")?

Answer ONLY with this JSON:
{"correspond": true}
or
{"correspond": false}

Be strict. If you are not sure, answer false.
PROMPT;

        try {
            $text = $this->groqVisionText($imageBase64, $mimeType, $prompt, 15, 0.0);
            $json = $this->extractJsonSafe($text);
            return (bool)($json['correspond'] ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Compare le nom de culture attendu avec le nom détecté par l'IA.
     * Gère les synonymes, variétés et traductions courants.
     */
    private function nomsCultureCorrespondent(string $attendu, string $detecte): bool
    {
        if (!$attendu || !$detecte) return false;

        // Correspondance exacte
        if ($attendu === $detecte) return true;

        // L'un contient l'autre (ex: "tomate cerise" contient "tomate")
        if (str_contains($detecte, $attendu) || str_contains($attendu, $detecte)) return true;

        // Table de synonymes / variétés / traductions
        // Chaque groupe = toutes les façons de nommer la même plante
        $groupes = [
            ['tomate', 'tomato', 'tomates', 'tomate cerise', 'tomate grappe', 'cherry tomato'],
            ['pomme de terre', 'potato', 'patate', 'pommes de terre', 'spud'],
            ['blé', 'wheat', 'blé dur', 'blé tendre', 'triticum'],
            ['maïs', 'corn', 'maize', 'mais'],
            ['olive', 'olivier', 'olives', 'olive tree', 'olea europaea'],
            ['vigne', 'raisin', 'grape', 'vignes', 'grapevine', 'vitis'],
            ['orge', 'barley', 'hordeum'],
            ['sorgho', 'sorghum'],
            ['tournesol', 'sunflower', 'helianthus'],
            ['piment', 'poivron', 'pepper', 'capsicum', 'chili', 'bell pepper'],
            ['aubergine', 'eggplant', 'brinjal'],
            ['courgette', 'zucchini', 'courgettes'],
            ['concombre', 'cucumber'],
            ['carotte', 'carrot'],
            ['oignon', 'onion'],
            ['ail', 'garlic'],
            ['laitue', 'salade', 'lettuce'],
            ['épinard', 'spinach'],
            ['haricot', 'bean', 'haricots', 'green bean'],
            ['pois', 'pea', 'pois chiche', 'chickpea'],
            ['fève', 'fava bean', 'broad bean'],
            ['lentille', 'lentil'],
            ['pastèque', 'watermelon'],
            ['melon', 'cantaloupe'],
            ['fraise', 'strawberry'],
            ['figuier', 'figue', 'fig', 'fig tree'],
            ['grenadier', 'grenade', 'pomegranate'],
            ['dattier', 'datte', 'palmier dattier', 'date palm', 'date tree'],
            ['amandier', 'amande', 'almond', 'almond tree'],
            ['pommier', 'pomme', 'apple', 'apple tree'],
            ['poirier', 'poire', 'pear', 'pear tree'],
            ['cerisier', 'cerise', 'cherry', 'cherry tree'],
            ['abricotier', 'abricot', 'apricot', 'apricot tree'],
            ['pêcher', 'pêche', 'peach', 'peach tree'],
            ['citronnier', 'citron', 'lemon', 'lemon tree'],
            ['oranger', 'orange', 'orange tree'],
            ['mandarinier', 'mandarine', 'tangerine', 'clementine'],
            ['caroubier', 'caroube', 'carob'],
            ['pistachier', 'pistache', 'pistachio'],
            ['noyer', 'noix', 'walnut'],
        ];

        foreach ($groupes as $groupe) {
            if (in_array($attendu, $groupe, true) && in_array($detecte, $groupe, true)) {
                return true;
            }
        }

        return false;
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

    // Appel Gemini Vision — conservé pour usage futur éventuel
    /** @phpstan-ignore method.unused */
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

    /**
     * @return array<string, mixed>
     */
    private function extractJsonSafe(string $text): array
    {
        $cleaned = preg_replace('/```json\s*/i', '', $text) ?? $text;
        $cleaned = preg_replace('/```\s*/', '', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);
        $start   = strpos($cleaned, '{');
        $end     = strrpos($cleaned, '}');

        if ($start === false || $end === false || $end <= $start) {
            return ['valide' => false, 'confiance' => 0, 'culture_detectee' => '', 'message' => 'Réponse IA non parseable'];
        }

        $json = json_decode(substr($cleaned, $start, $end - $start + 1), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['valide' => false, 'confiance' => 0, 'culture_detectee' => '', 'message' => 'Réponse IA invalide'];
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
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