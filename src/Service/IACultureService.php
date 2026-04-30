<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class IACultureService
{
    private const GEMINI_URL =
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    private string $geminiKey;
    private string $groqKey;

    public function __construct(private HttpClientInterface $client)
    {
        $this->geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
        $this->groqKey   = $_ENV['GROQ_API_KEY']   ?? '';
    }

    // ═══════════════════════════════════════════════════════════
    // RECOMMANDATION — Groq LLaMA 3.3
    // ═══════════════════════════════════════════════════════════

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
                        ['role' => 'system', 'content' => $this->buildSystemPrompt()],
                        ['role' => 'user',   'content' => $this->buildPrompt($parcelle)],
                    ],
                ],
                'timeout' => 15,
            ]
        );

        $data = $response->toArray(false);

        if (isset($data['error'])) {
            throw new \Exception('Groq: ' . ($data['error']['message'] ?? json_encode($data['error'])));
        }

        $text = $data['choices'][0]['message']['content'] ?? '';
        if (!$text) {
            throw new \Exception('Réponse Groq vide');
        }

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

JSON attendu (premier caractère = {, dernier = }):
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
    // ANALYSE IMAGE — Gemini Vision
    // ═══════════════════════════════════════════════════════════

    public function analyserImage($file, string $nomCulture = ''): array
    {
        try {
            $imageData = base64_encode(file_get_contents($file->getPathname()));
            $mimeType  = $file->getMimeType();

            
            $validation = $this->validerImageAgricole($imageData, $mimeType);
            if (!$validation['est_agricole']) {
                return [
                    'image_invalide' => true,
                    'raison'         => $validation['raison'],
                ];
            }

           
            $text = $this->geminiVisionText(
                $imageData,
                $mimeType,
                $this->buildImagePrompt($nomCulture),
                30
            );

            return $this->extractJsonSafe($text);

        } catch (\Throwable $e) {
            return [
                'sante_globale'           => 'MOYEN',
                'score_sante'             => 50,
                'resume'                  => 'Erreur analyse IA',
                'stade_croissance'        => 'Inconnu',
                'stress_hydrique'         => ['niveau' => 'INCONNU', 'description' => $e->getMessage()],
                'diagnostic_principal'    => ['probleme' => 'Erreur IA', 'description' => $e->getMessage(), 'confiance' => 0],
                'diagnostics_secondaires' => [],
                'recommandations'         => [],
            ];
        }
    }

    private function buildImagePrompt(string $nomCulture = ''): string
    {
        $mention = $nomCulture ? "Culture analysée : {$nomCulture}." : '';
        return "Tu es un expert en phytopathologie et agriculture. {$mention}

Analyse cette image et réponds UNIQUEMENT en JSON:
{
  \"sante_globale\": \"BON\",
  \"score_sante\": 80,
  \"resume\": \"description\",
  \"stade_croissance\": \"végétatif\",
  \"stress_hydrique\": {\"niveau\": \"AUCUN\", \"description\": \"normal\"},
  \"diagnostic_principal\": {\"probleme\": \"Sain\", \"description\": \"bonne santé\", \"confiance\": 90},
  \"diagnostics_secondaires\": [],
  \"recommandations\": [{\"priorite\": \"PREVENTIVE\", \"action\": \"action\", \"detail\": \"détail\"}]
}

sante_globale: BON|MOYEN|MAUVAIS|CRITIQUE
stress_hydrique.niveau: AUCUN|FAIBLE|MODERE|ELEVE|CRITIQUE
recommandations.priorite: URGENTE|NORMALE|PREVENTIVE";
    }

    // ═══════════════════════════════════════════════════════════
    // VALIDATION IMAGE CULTURE — corrigée
    // ═══════════════════════════════════════════════════════════

  public function validerImageCulture(
    string $imageBase64,
    string $mimeType,
    string $culture
): array {

    $prompt = "Tu es un botaniste expert avec une excellente vision.

ÉTAPE 1 — Décris précisément ce que tu vois dans l'image :
- Quelle plante ? Quelle couleur ? Quelle forme ?
- Feuilles, fruits, fleurs, tiges ?

ÉTAPE 2 — Compare avec la culture attendue : \"{$culture}\"

ÉTAPE 3 — Réponds UNIQUEMENT avec ce JSON (sans aucun texte avant ou après) :
{
  \"culture_detectee\": \"nom exact de ce que tu vois\",
  \"valide\": true,
  \"confiance\": 90,
  \"message\": \"raison courte\"
}

RÈGLE PRINCIPALE :
- Regarde VRAIMENT l'image et identifie la plante correctement
- Ne devine pas à partir du nom attendu — identifie d'abord, compare ensuite

valide = true si la plante vue correspond à \"{$culture}\"
valide = false si la plante vue est DIFFÉRENTE de \"{$culture}\"
valide = true si image floue ou difficile à identifier (doute)
valide = false si clairement une autre plante

EXEMPLES CONCRETS :
- Tu vois des dattes/palmier dattier, attendu \"Datte\" → valide=true, culture_detectee=\"Datte\"
- Tu vois des dattes/palmier dattier, attendu \"Tomate\" → valide=false, culture_detectee=\"Datte\"
- Tu vois une laitue verte, attendu \"Tomate\" → valide=false, culture_detectee=\"Laitue\"
- Tu vois une tomate rouge, attendu \"Tomate\" → valide=true, culture_detectee=\"Tomate\"
- Tu vois un olivier, attendu \"Olivier\" → valide=true, culture_detectee=\"Olivier\"
- Image floue, difficile à identifier → valide=true, confiance=40

Réponds uniquement en JSON.";

    try {
        $text = $this->geminiVisionText(
            $imageBase64,
            $mimeType,
            $prompt,
            20,
            0.0  
        );

        $json = $this->extractJsonSafe($text);

        $confiance       = (int)($json['confiance'] ?? 70);
        $valide          = (bool)($json['valide'] ?? true);
        $cultureDetectee = trim($json['culture_detectee'] ?? '');
        $message         = trim($json['message'] ?? '');

        if ($confiance < 40) {
            return $this->accepterImage($culture, 'Image difficile à identifier — acceptée');
        }

       
       
        if ($valide
            && $confiance >= 75
            && $cultureDetectee
            && !$this->culturesCompatibles(
                strtolower($culture),
                strtolower($cultureDetectee)
            )
        ) {
            return [
                'valide'           => false,
                'confiance'        => $confiance,
                'culture_detectee' => $cultureDetectee,
                'message'          => "Vu : {$cultureDetectee}, attendu : {$culture}",
            ];
        }

        return [
            'valide'           => $valide,
            'confiance'        => max(50, $confiance),
            'culture_detectee' => $cultureDetectee ?: $culture,
            'message'          => $message ?: ($valide ? 'Image validée' : 'Image incorrecte'),
        ];

    } catch (\Throwable $e) {
        return $this->accepterImage($culture);
    }
}

private function culturesCompatibles(string $attendue, string $detectee): bool
{
    
    if ($attendue === $detectee) return true;

   
    if (str_contains($attendue, $detectee) || str_contains($detectee, $attendue)) {
        return true;
    }

    
    $familles = [
        ['tomate', 'tomato', 'tomates'],
        ['courgette', 'courgettes', 'zucchini', 'courge'],
        ['laitue', 'salade', 'laitues', 'salades', 'romaine', 'batavia'],
        ['olivier', 'olive', 'oliviers', 'olives'],
        ['blé', 'ble', 'wheat', 'céréale', 'cereale'],
        ['mais', 'maïs', 'corn'],
        ['piment', 'poivron', 'piments', 'poivrons', 'pepper'],
        ['pomme de terre', 'patate', 'potato'],
        ['carotte', 'carottes'],
        ['oignon', 'oignons', 'echalote'],
        ['ail', 'ails'],
        ['concombre', 'concombres'],
        ['aubergine', 'aubergines'],
        ['haricot', 'haricots', 'bean'],
        ['pois', 'petit pois'],
        ['pastèque', 'pasteque', 'melon'],
        ['datte', 'dattes', 'palmier', 'phoeniciculture'],
        ['figue', 'figues', 'figuier'],
        ['orge', 'avoine', 'seigle'],
        ['tournesol', 'sunflower'],
        ['betterave', 'betteraves'],
    ];

    foreach ($familles as $famille) {
        $attendueInFamille = false;
        $detecteeInFamille = false;

        foreach ($famille as $membre) {
            if (str_contains($attendue, $membre) || str_contains($membre, $attendue)) {
                $attendueInFamille = true;
            }
            if (str_contains($detectee, $membre) || str_contains($membre, $detectee)) {
                $detecteeInFamille = true;
            }
        }

        if ($attendueInFamille && $detecteeInFamille) return true;
    }

    return false;
}

    
    private function accepterImage(string $culture): array
    {
        return [
            'valide'           => true,
            'confiance'        => 75,
            'culture_detectee' => $culture,
            'message'          => 'Image acceptée',
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // VALIDATION AGRICOLE INTERNE
    // ═══════════════════════════════════════════════════════════

    private function validerImageAgricole(string $imageData, string $mimeType): array
    {
        $prompt = "Regarde cette image.

Réponds UNIQUEMENT avec ce JSON:
{
  \"est_agricole\": true,
  \"raison\": \"ce que tu vois\"
}

RÈGLES:
- est_agricole = true si : plante, feuille, fruit, légume, champ, culture, sol
- est_agricole = false si : personne, animal, voiture, bâtiment, objet, dessin
- En cas de doute → est_agricole = true";

        try {
            $text = $this->geminiVisionText($imageData, $mimeType, $prompt, 10);
            $json = $this->extractJsonSafe($text);

            return [
                'est_agricole' => (bool)($json['est_agricole'] ?? true),
                'raison'       => $json['raison'] ?? '',
            ];
        } catch (\Throwable) {
            // Fail open — en cas d'erreur, on laisse passer
            return ['est_agricole' => true, 'raison' => ''];
        }
    }

    // ═══════════════════════════════════════════════════════════
    
    // Retourne le texte brut de la réponse 
    // ═══════════════════════════════════════════════════════════

 private function geminiVisionText(
    string $imageBase64,
    string $mimeType,
    string $prompt,
    int    $timeout = 30,
    float  $temperature = 0.1  
): string {
    $url = self::GEMINI_URL . '?key=' . $this->geminiKey;

    $response = $this->client->request('POST', $url, [
        'headers' => ['Content-Type' => 'application/json'],
        'json'    => [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data'      => $imageBase64,
                        ],
                    ],
                ],
            ]],
            'generationConfig' => [
                'temperature'     => $temperature,  
                'maxOutputTokens' => 500,
            ],
        ],
        'timeout' => $timeout,
    ]);

    $data = $response->toArray(false);

    if (isset($data['error'])) {
        throw new \RuntimeException(
            'Gemini error: ' . ($data['error']['message'] ?? json_encode($data['error']))
        );
    }

    if (isset($data['promptFeedback']['blockReason'])) {
        throw new \RuntimeException(
            'Gemini bloqué: ' . $data['promptFeedback']['blockReason']
        );
    }

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if (!$text) {
        throw new \RuntimeException('Réponse Gemini vide');
    }

    return $text;
}

    // ═══════════════════════════════════════════════════════════
    // PARSER JSON — robuste
    // ═══════════════════════════════════════════════════════════

    private function extractJsonSafe(string $text): array
    {
        
        $text = preg_replace('/```json\s*/i', '', $text);
        $text = preg_replace('/```\s*/',      '', $text);
        $text = trim($text);

       
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
         
            return [
                'valide'           => true,
                'confiance'        => 70,
                'culture_detectee' => '',
                'message'          => 'Réponse acceptée',
            ];
        }

        $jsonString = substr($text, $start, $end - $start + 1);
        $json       = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'valide'           => true,
                'confiance'        => 70,
                'culture_detectee' => '',
                'message'          => 'Réponse acceptée',
            ];
        }

        return $json;
    }
}



