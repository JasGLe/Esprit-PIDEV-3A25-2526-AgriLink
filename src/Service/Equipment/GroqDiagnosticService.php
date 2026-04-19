<?php

namespace App\Service\Equipment;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * GroqDiagnosticService
 * ─────────────────────────────────────────────────────────────────────────────
 * Service responsable d'appeler l'API Groq (LLM llama3-8b-8192) pour générer
 * un diagnostic IA sur l'état d'un équipement agricole.
 *
 * Fonctionnement :
 *  1. Reçoit un tableau de données structurées sur l'équipement (depuis le controller)
 *  2. Construit un prompt utilisateur détaillé à partir de ces données
 *  3. Envoie une requête HTTP POST à https://api.groq.com/openai/v1/chat/completions
 *  4. Parse la réponse JSON retournée par le LLM
 *  5. Retourne un tableau PHP prêt à être sérialisé en JsonResponse
 * ─────────────────────────────────────────────────────────────────────────────
 */
class GroqDiagnosticService
{
    // URL de l'API Groq — compatible OpenAI Chat Completions
    private const GROQ_API_URL = 'https://api.groq.com/openai/v1/chat/completions';

    /**
     * System prompt envoyé à Groq pour cadrer strictement le comportement du LLM.
     * Il interdit toute réponse hors diagnostic technique agricole.
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

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $groqApiKey,
        private readonly string $groqModel
    ) {}

    /**
     * Génère un diagnostic IA pour un équipement.
     *
     * @param array $equipementData  Données structurées de l'équipement (depuis DiagnosticController)
     * @param array $maintenances    Les 10 dernières maintenances (objets Maintenance)
     *
     * @return array  Le diagnostic parsé depuis la réponse JSON du LLM
     * @throws \RuntimeException  En cas d'erreur HTTP ou de réponse non-JSON
     */
    public function diagnostiquer(array $equipementData, array $maintenances): array
    {
        // ── 1. Construire le prompt utilisateur ────────────────────────────────
        $prompt = $this->buildUserPrompt($equipementData, $maintenances);

        // ── 2. Appel HTTP vers l'API Groq ──────────────────────────────────────
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
                        // Message utilisateur : les données réelles de l'équipement
                        ['role' => 'user',   'content' => $prompt],
                    ],
                    'max_tokens'  => 1024,
                    'temperature' => 0.3, // Réponses précises et stables (moins créatif)
                ],
                'timeout' => 30,
            ]);

            // Lire le corps brut AVANT toArray() pour capturer l'erreur Groq si 4xx/5xx
            // getContent(false) ne lève pas d'exception sur les codes d'erreur HTTP
            $statusCode  = $response->getStatusCode();
            $rawContent  = $response->getContent(false);

            if ($statusCode !== 200) {
                // Extraire le message d'erreur du JSON retourné par Groq si possible
                $errData = json_decode($rawContent, true);
                $errMsg  = $errData['error']['message'] ?? $rawContent;
                throw new \RuntimeException('Groq API ' . $statusCode . ' : ' . $errMsg);
            }

            $data = json_decode($rawContent, true);

        } catch (\RuntimeException $e) {
            // Propager les erreurs Groq explicites
            throw $e;
        } catch (\Throwable $e) {
            // Erreur réseau / transport
            throw new \RuntimeException('Erreur réseau vers l\'API Groq : ' . $e->getMessage());
        }

        // ── 3. Extraire le contenu texte de la réponse du LLM ─────────────────
        $content = $data['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            throw new \RuntimeException('Réponse inattendue de l\'API Groq (pas de contenu).');
        }

        // ── 4. Nettoyer et parser le JSON retourné par le LLM ─────────────────
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
     * Construit le prompt utilisateur avec TOUTES les données disponibles
     * de l'équipement et l'historique des maintenances.
     *
     * Plus les données sont complètes, plus le diagnostic sera précis.
     */
    private function buildUserPrompt(array $d, array $maintenances): string
    {
        // ── Infos de base ──────────────────────────────────────────────────────
        $lines = [];
        $lines[] = "=== DONNÉES ÉQUIPEMENT ===";
        $lines[] = "Catégorie    : " . ($d['categorie'] ?? 'Non renseigné');
        $lines[] = "Nom          : " . ($d['nom'] ?? 'Non renseigné');
        $lines[] = "Type         : " . ($d['type'] ?? 'Non renseigné');
        $lines[] = "Marque       : " . ($d['marque'] ?? 'Non renseigné');
        $lines[] = "Modèle       : " . ($d['modele'] ?? 'Non renseigné');
        $lines[] = "Statut actuel: " . ($d['statut'] ?? 'Non renseigné');
        $lines[] = "Description  : " . ($d['description'] ?? 'Aucune description fournie');

        // ── Âge de l'équipement ────────────────────────────────────────────────
        if (!empty($d['dateAcquisition'])) {
            $lines[] = "Date acquisition : " . $d['dateAcquisition'];
            $lines[] = "Âge estimé       : " . $d['ageAnnees'] . " an(s)";
        }

        // ── Données véhicule (uniquement si catégorie Véhicule Motorisé) ───────
        if ($d['isVehicule'] ?? false) {
            $lines[] = "";
            $lines[] = "=== DONNÉES VÉHICULE ===";

            // Kilométrage
            $kmActuel = $d['kilometrageActuel'] ?? null;
            $kmSeuil  = $d['seuilKmMaintenance'] ?? null;
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

            if ($kmDernier !== null && $kmActuel !== null) {
                $parcourus = $kmActuel - $kmDernier;
                $lines[] = "Km depuis dernière maintenance : {$parcourus} km (dernière à {$kmDernier} km)";
            }

            // Heures
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

            if ($hDernier !== null && $hActuel !== null) {
                $effectuees = $hActuel - $hDernier;
                $lines[] = "Heures depuis dernière maintenance : {$effectuees} h (dernière à {$hDernier} h)";
            }
        }

        // ── Maintenance périodique (jours) ────────────────────────────────────
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

        // ── Historique maintenances ────────────────────────────────────────────
        $lines[] = "";
        $lines[] = "=== HISTORIQUE DES MAINTENANCES (10 dernières) ===";

        if (empty($maintenances)) {
            $lines[] = "Aucune maintenance enregistrée.";
        } else {
            foreach ($maintenances as $i => $m) {
                $num = $i + 1;
                $datePlan = $m->getDatePlanifiee()?->format('d/m/Y') ?? '—';
                $dateReel = $m->getDateReelle()?->format('d/m/Y') ?? 'Non effectuée';
                $cout     = $m->getCout() ? $m->getCout() . ' DT' : 'Non renseigné';

                // Calcul du retard éventuel entre date planifiée et date réelle
                $retardStr = '';
                if ($m->getDatePlanifiee() && $m->getDateReelle()) {
                    $diff = $m->getDatePlanifiee()->diff($m->getDateReelle());
                    $jours = (int) $diff->format('%r%a'); // positif = retard
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

        // ── Instruction finale — format de réponse attendu ────────────────────
        $lines[] = "";
        $lines[] = "Réponds UNIQUEMENT avec le JSON suivant, sans texte autour :";
        $lines[] = '{"indice_sante":"Excellent|Bon|Attention|Critique","score":0-100,"resume":"...","analyse_par_critere":{"statut":"phrase courte","description":"phrase courte","kilometrage":"phrase courte ou null","heures":"phrase courte ou null","maintenance":"phrase courte"},"alertes":[],"recommandations":[],"prochaine_maintenance_estimee":"durée relative ex: Dans 30 jours | Dans 3 mois | Immédiatement — JAMAIS une date absolue passée"}';
        $lines[] = "IMPORTANT : prochaine_maintenance_estimee doit être une durée RELATIVE (ex: 'Dans 30 jours', 'Dans 3 mois', 'Immédiatement') et NON une date absolue. Tous les champs de analyse_par_critere doivent être des strings courts, jamais des objets JSON.";

        return implode("\n", $lines);
    }
}
