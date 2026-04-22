<?php

namespace App\Service\Equipment;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * GroqDiagnosticService
 * ─────────────────────────────────────────────────────────────────────────────
 * Service IA responsable du diagnostic textuel d'un équipement agricole.
 *
 * Utilise l'API Groq avec le modèle LLaMA 3.1 8B Instant pour analyser
 * l'état d'un équipement à partir de ses données structurées (statut, kilométrage,
 * heures d'utilisation, seuils de maintenance, historique des maintenances).
 *
 * Flux d'exécution :
 *  1. Reçoit un tableau de données structurées de l'équipement (depuis DiagnosticController)
 *  2. Construit un prompt utilisateur détaillé via buildUserPrompt()
 *  3. Envoie une requête HTTP POST à l'API Groq (format OpenAI Chat Completions)
 *  4. Nettoie et parse la réponse JSON retournée par le LLM
 *  5. Retourne un tableau PHP prêt à être sérialisé en JsonResponse
 *
 * Format de réponse du LLM (JSON strict) :
 * {
 *   "indice_sante": "Excellent|Bon|Attention|Critique",
 *   "score": 0-100,
 *   "resume": "...",
 *   "analyse_par_critere": {
 *     "statut": "...", "description": "...",
 *     "kilometrage": "...|null", "heures": "...|null", "maintenance": "..."
 *   },
 *   "alertes": ["..."],
 *   "recommandations": ["..."],
 *   "prochaine_maintenance_estimee": "Dans X jours|mois"
 * }
 *
 * Paramétrage :
 *  - GROQ_API_KEY  : clé API (env var)
 *  - GROQ_MODEL    : modèle à utiliser (env var, défaut llama-3.1-8b-instant)
 *  - temperature=0.3 : réponses précises et stables (moins créatif que le chat)
 * ─────────────────────────────────────────────────────────────────────────────
 */
class GroqDiagnosticService
{
    /** URL de l'API Groq — compatible OpenAI Chat Completions. */
    private const GROQ_API_URL = 'https://api.groq.com/openai/v1/chat/completions';

    /**
     * Prompt système : cadre strictement le LLM sur le diagnostic agricole.
     *
     * Interdit toute réponse hors diagnostic technique d'équipement agricole.
     * Exige une réponse en JSON pur (sans markdown, sans texte avant/après).
     */
    private const SYSTEM_PROMPT = <<<PROMPT
Tu es un expert en diagnostic d'équipements agricoles.
Tu analyses UNIQUEMENT l'état technique et la santé d'un équipement
basé sur ses données réelles : statut, description, kilométrage, heures
d'utilisation, historique de maintenances.
Tu ne réponds à RIEN d'autre qu'un diagnostic technique d'équipement agricole.
tu peux donner des recommendation pour les maintenance a effectuer tel que la date les pieces etc
Si les données sont insuffisantes, tu bases ton analyse sur ce qui est disponible.
Tu réponds UNIQUEMENT en JSON valide, sans texte avant ni après, sans markdown.
PROMPT;

    /**
     * @param HttpClientInterface $httpClient  Client HTTP Symfony pour appeler l'API Groq
     * @param string              $groqApiKey  Clé API Groq (depuis paramètre Symfony groq_api_key)
     * @param string              $groqModel   Modèle LLM à utiliser (depuis GROQ_MODEL env var)
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $groqApiKey,
        private readonly string $groqModel
    ) {}

    /**
     * Génère un diagnostic IA textuel pour un équipement agricole.
     *
     * @param array $equipementData  Données structurées de l'équipement (depuis DiagnosticController).
     *                               Clés : categorie, nom, type, marque, modele, statut, description,
     *                               isVehicule, dateAcquisition, ageAnnees, kilometrageActuel,
     *                               seuilKmMaintenance, heuresUtilisation, seuilHeuresMaintenance,
     *                               seuilJoursMaintenance, dateDerniereMaintenance, joursDepuisMaintenance
     * @param array $maintenances    Les 10 dernières maintenances (objets Maintenance)
     *
     * @return array  Diagnostic parsé depuis la réponse JSON du LLM (voir format ci-dessus)
     *
     * @throws \RuntimeException  Si l'API retourne une erreur HTTP, une réponse vide ou un JSON invalide
     */
    public function diagnostiquer(array $equipementData, array $maintenances): array
    {
        // ── 1. Construire le prompt utilisateur à partir des données ──────────
        $prompt = $this->buildUserPrompt($equipementData, $maintenances);

        // ── 2. Appel HTTP vers l'API Groq ────────────────────────────────────
        try {
            $response = $this->httpClient->request('POST', self::GROQ_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => $this->groqModel,
                    'messages'    => [
                        // Message système : cadre le comportement du LLM
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        // Message utilisateur : données réelles de l'équipement
                        ['role' => 'user',   'content' => $prompt],
                    ],
                    'max_tokens'  => 1024,
                    'temperature' => 0.3, // réponses précises et stables
                ],
                'timeout' => 30,
            ]);

            // getContent(false) ne lève pas d'exception sur 4xx/5xx — on gère manuellement
            $statusCode  = $response->getStatusCode();
            $rawContent  = $response->getContent(false);

            if ($statusCode !== 200) {
                // Extraire le message d'erreur du JSON Groq si disponible
                $errData = json_decode($rawContent, true);
                $errMsg  = $errData['error']['message'] ?? $rawContent;
                throw new \RuntimeException('Groq API ' . $statusCode . ' : ' . $errMsg);
            }

            $data = json_decode($rawContent, true);

        } catch (\RuntimeException $e) {
            // Propager les erreurs Groq explicites vers le contrôleur
            throw $e;
        } catch (\Throwable $e) {
            // Erreur réseau / transport Symfony HttpClient
            throw new \RuntimeException('Erreur réseau vers l\'API Groq : ' . $e->getMessage());
        }

        // ── 3. Extraire le contenu texte de la réponse ───────────────────────
        $content = $data['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            throw new \RuntimeException('Réponse inattendue de l\'API Groq (pas de contenu).');
        }

        // ── 4. Nettoyer et parser le JSON retourné par le LLM ────────────────
        // Le LLM peut parfois ajouter des backticks ```json ... ``` malgré la consigne
        $content = preg_replace('/^```json\s*/i', '', trim($content));
        $content = preg_replace('/```\s*$/', '', $content);
        $content = trim($content);

        $diagnostic = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('La réponse IA n\'est pas un JSON valide : ' . $content);
        }

        return $diagnostic;
    }

    /**
     * Construit le prompt utilisateur avec toutes les données disponibles.
     *
     * Structure du prompt en 4 sections :
     *  1. Données équipement (infos générales + âge)
     *  2. Données véhicule (uniquement si isVehicule=true) : km et heures avec dépassements
     *  3. Maintenance périodique : seuil jours + date dernière maintenance + retard éventuel
     *  4. Historique des 10 dernières maintenances
     *  5. Instruction finale : format JSON attendu en réponse
     *
     * Plus les données sont complètes, plus le diagnostic sera précis.
     * Les champs null sont affichés comme "Non renseigné" ou omis selon leur pertinence.
     *
     * @param array $d            Données de l'équipement
     * @param array $maintenances Objets Maintenance (les 10 dernières)
     *
     * @return string  Prompt texte prêt à être envoyé au LLM
     */
    private function buildUserPrompt(array $d, array $maintenances): string
    {
        // ── Section 1 : Informations générales de l'équipement ───────────────
        $lines = [];
        $lines[] = "=== DONNÉES ÉQUIPEMENT ===";
        $lines[] = "Catégorie    : " . ($d['categorie'] ?? 'Non renseigné');
        $lines[] = "Nom          : " . ($d['nom'] ?? 'Non renseigné');
        $lines[] = "Type         : " . ($d['type'] ?? 'Non renseigné');
        $lines[] = "Marque       : " . ($d['marque'] ?? 'Non renseigné');
        $lines[] = "Modèle       : " . ($d['modele'] ?? 'Non renseigné');
        $lines[] = "Statut actuel: " . ($d['statut'] ?? 'Non renseigné');
        $lines[] = "Description  : " . ($d['description'] ?? 'Aucune description fournie');

        // ── Âge de l'équipement (calculé depuis dateAcquisition) ─────────────
        if (!empty($d['dateAcquisition'])) {
            $lines[] = "Date acquisition : " . $d['dateAcquisition'];
            $lines[] = "Âge estimé       : " . $d['ageAnnees'] . " an(s)";
        }

        // ── Section 2 : Données véhicule (uniquement si Véhicule Motorisé) ────
        if ($d['isVehicule'] ?? false) {
            $lines[] = "";
            $lines[] = "=== DONNÉES VÉHICULE ===";

            // Kilométrage avec indication du dépassement du seuil
            $kmActuel  = $d['kilometrageActuel'] ?? null;
            $kmSeuil   = $d['seuilKmMaintenance'] ?? null;
            $kmDernier = $d['kilometrageDerniereMaintenance'] ?? null;

            if ($kmActuel !== null) {
                $ligne = "Kilométrage actuel : {$kmActuel} km";
                if ($kmSeuil !== null) {
                    $diff = $kmActuel - $kmSeuil;
                    $ligne .= " / seuil {$kmSeuil} km";
                    $ligne .= $diff > 0
                        ? " → DÉPASSÉ de {$diff} km ⚠️"
                        : " → reste " . abs($diff) . " km avant seuil";
                }
                $lines[] = $ligne;
            }

            // Kilométrage parcouru depuis la dernière maintenance
            if ($kmDernier !== null && $kmActuel !== null) {
                $parcourus = $kmActuel - $kmDernier;
                $lines[] = "Km depuis dernière maintenance : {$parcourus} km (dernière à {$kmDernier} km)";
            }

            // Heures d'utilisation avec indication du dépassement du seuil
            $hActuel  = $d['heuresUtilisation'] ?? null;
            $hSeuil   = $d['seuilHeuresMaintenance'] ?? null;
            $hDernier = $d['heuresDerniereMaintenance'] ?? null;

            if ($hActuel !== null) {
                $ligne = "Heures utilisation : {$hActuel} h";
                if ($hSeuil !== null) {
                    $diff = $hActuel - $hSeuil;
                    $ligne .= " / seuil {$hSeuil} h";
                    $ligne .= $diff > 0
                        ? " → DÉPASSÉ de {$diff} h ⚠️"
                        : " → reste " . abs($diff) . " h avant seuil";
                }
                $lines[] = $ligne;
            }

            // Heures effectuées depuis la dernière maintenance
            if ($hDernier !== null && $hActuel !== null) {
                $effectuees = $hActuel - $hDernier;
                $lines[] = "Heures depuis dernière maintenance : {$effectuees} h (dernière à {$hDernier} h)";
            }
        }

        // ── Section 3 : Maintenance périodique (tous équipements) ────────────
        $lines[] = "";
        $lines[] = "=== MAINTENANCE PÉRIODIQUE ===";
        $lines[] = "Seuil maintenance (jours) : " . ($d['seuilJoursMaintenance'] ?? 'Non défini');

        if (!empty($d['dateDerniereMaintenance'])) {
            $lines[] = "Dernière maintenance : " . $d['dateDerniereMaintenance'];
            $lines[] = "Jours écoulés depuis dernière maintenance : " . $d['joursDepuisMaintenance'];

            if (!empty($d['seuilJoursMaintenance'])) {
                $retard = $d['joursDepuisMaintenance'] - $d['seuilJoursMaintenance'];
                if ($retard > 0) {
                    $lines[] = "→ RETARD de {$retard} jours sur le seuil ⚠️";
                } else {
                    $lines[] = "→ Prochain seuil dans " . abs($retard) . " jours";
                }
            }
        } else {
            $lines[] = "Dernière maintenance : Inconnue";
        }

        // ── Section 4 : Historique des 10 dernières maintenances ─────────────
        $lines[] = "";
        $lines[] = "=== HISTORIQUE DES MAINTENANCES (10 dernières) ===";

        if (empty($maintenances)) {
            $lines[] = "Aucune maintenance enregistrée.";
        } else {
            foreach ($maintenances as $i => $m) {
                $num      = $i + 1;
                $datePlan = $m->getDatePlanifiee()?->format('d/m/Y') ?? '—';
                $dateReel = $m->getDateReelle()?->format('d/m/Y') ?? 'Non effectuée';
                $cout     = $m->getCout() ? $m->getCout() . ' DT' : 'Non renseigné';

                // Calcul du retard éventuel entre date planifiée et date réelle
                $retardStr = '';
                if ($m->getDatePlanifiee() && $m->getDateReelle()) {
                    $diff  = $m->getDatePlanifiee()->diff($m->getDateReelle());
                    $jours = (int) $diff->format('%r%a'); // positif = retard, négatif = en avance
                    if ($jours > 0) {
                        $retardStr = " (retard {$jours} j)";
                    } elseif ($jours < 0) {
                        $retardStr = " (en avance " . abs($jours) . " j)";
                    }
                }

                $lines[] = "  [{$num}] Type: " . ($m->getType() ?? '—')
                    . " | Catégorie: " . ($m->getCategorie() ?? '—')
                    . " | Planifiée: {$datePlan} | Réelle: {$dateReel}{$retardStr}"
                    . " | Coût: {$cout}"
                    . " | Statut: " . ($m->getStatut() ?? '—');

                if ($m->getDescription()) {
                    $lines[] = "     Description: " . $m->getDescription();
                }
            }
        }

        // ── Section 5 : Instruction finale — format JSON attendu ─────────────
        $lines[] = "";
        $lines[] = "Réponds UNIQUEMENT avec le JSON suivant, sans texte autour :";
        $lines[] = '{"indice_sante":"Excellent|Bon|Attention|Critique","score":0-100,"resume":"...","analyse_par_critere":{"statut":"phrase courte","description":"phrase courte","kilometrage":"phrase courte ou null","heures":"phrase courte ou null","maintenance":"phrase courte"},"alertes":[],"recommandations":[],"prochaine_maintenance_estimee":"durée relative ex: Dans 30 jours | Dans 3 mois | Immédiatement — JAMAIS une date absolue passée"}';
        $lines[] = "IMPORTANT : prochaine_maintenance_estimee doit être une durée RELATIVE (ex: 'Dans 30 jours', 'Dans 3 mois', 'Immédiatement') et NON une date absolue. Tous les champs de analyse_par_critere doivent être des strings courts, jamais des objets JSON.";

        return implode("\n", $lines);
    }
}
