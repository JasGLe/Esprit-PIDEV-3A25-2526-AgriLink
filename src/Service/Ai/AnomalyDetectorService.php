<?php

namespace App\Service\Ai;

use App\Entity\Activity\Activite;
use App\Repository\Activity\ActiviteRepository;
use App\Service\Activity\GeminiActivityService;

class AnomalyDetectorService
{
    /**
     * @var array<string, array{min: float|int, max: float|int}>
     */
    private array $normalCosts = [
        'SEMIS' => ['min' => 20, 'max' => 100],
        'IRRIGATION' => ['min' => 30, 'max' => 150],
        'RECOLTE' => ['min' => 50, 'max' => 300],
        'TRAITEMENT' => ['min' => 25, 'max' => 200],
        'TAILLE' => ['min' => 15, 'max' => 100],
        'AUTRE' => ['min' => 10, 'max' => 500],
    ];

    /**
     * @var array<string, array{min: float|int, max: float|int}>
     */
    private array $normalDurations = [
        'SEMIS' => ['min' => 1, 'max' => 8],
        'IRRIGATION' => ['min' => 2, 'max' => 12],
        'RECOLTE' => ['min' => 4, 'max' => 16],
        'TRAITEMENT' => ['min' => 1, 'max' => 6],
        'TAILLE' => ['min' => 2, 'max' => 10],
        'AUTRE' => ['min' => 0.5, 'max' => 24],
    ];

    public function __construct(
        private readonly ActiviteRepository $activiteRepository,
        private readonly GeminiActivityService $geminiService,
    ) {
    }

    /**
     * Détecte les anomalies dans les activités des 10 prochains jours
     *
     * @return list<array{activity: Activite, issues: list<array<string, mixed>>}>
     */
    public function detectAnomalies(int $userId): array
    {
        $today = new \DateTime();
        $tenDaysLater = (new \DateTime())->modify('+10 days');

        // Récupère les activités des 10 prochains jours
        $activities = $this->activiteRepository->findBetweenDates($today, $tenDaysLater);
        
        // Filtre par utilisateur
        $activities = array_filter($activities, function (Activite $a) use ($userId): bool {
            return $a->getIdAgriculteur() === $userId;
        });
        $activities = array_values($activities);

        $anomalies = [];

        /** @var Activite $activity */
        foreach ($activities as $activity) {
            $activityAnomalies = $this->checkActivityAnomalies($activity, $activities);
            if (!empty($activityAnomalies)) {
                $anomalies[] = [
                    'activity' => $activity,
                    'issues' => $activityAnomalies,
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Vérifie les anomalies pour une activité spécifique
     *
     * @param list<Activite> $allActivities
     * @return list<array<string, mixed>>
     */
    private function checkActivityAnomalies(Activite $activity, array $allActivities): array
    {
        $issues = [];

        // 1. Vérifier le coût anormal
        $issues = array_merge($issues, $this->checkCostAnomaly($activity));

        // 2. Vérifier la durée anormale
        $issues = array_merge($issues, $this->checkDurationAnomaly($activity));

        // 3. Vérifier l'ordre logique des activités
        $issues = array_merge($issues, $this->checkSequenceLogic($activity, $allActivities));

        return $issues;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function checkCostAnomaly(Activite $activity): array
    {
        $issues = [];
        $type = $activity->getTypeActivite();
        $cost = (float) $activity->getCoutEstime();

        if ($cost === 0.0) {
            return $issues; // Skip if no cost
        }

        $normal = $this->normalCosts[$type] ?? null;
        if ($normal === null) {
            return $issues;
        }

        if ($cost < $normal['min']) {
            $issues[] = [
                'type' => 'LOW_COST',
                'message' => "Coût anormalement bas pour {$type}: {$cost} DT (normal: {$normal['min']}-{$normal['max']} DT)",
                'severity' => 'warning',
            ];
        } elseif ($cost > $normal['max']) {
            $issues[] = [
                'type' => 'HIGH_COST',
                'message' => "Coût anormalement élevé pour {$type}: {$cost} DT (normal: {$normal['min']}-{$normal['max']} DT)",
                'severity' => 'error',
            ];
        }

        return $issues;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function checkDurationAnomaly(Activite $activity): array
    {
        $issues = [];
        $type = $activity->getTypeActivite();
        
        if ($activity->getDateDebut() === null || $activity->getDateFin() === null) {
            return $issues;
        }

        $start = $activity->getDateDebut();
        $end = $activity->getDateFin();
        if ($start === null || $end === null) {
            return $issues;
        }
        $diff     = $end->diff($start);
        $duration = (int) ($diff->days * 24 + $diff->h);
        $normal = $this->normalDurations[$type] ?? null;

        if ($normal === null) {
            return $issues;
        }

        if ($duration < $normal['min']) {
            $issues[] = [
                'type' => 'SHORT_DURATION',
                'message' => "Durée anormalement courte pour {$type}: {$duration}h (normal: {$normal['min']}-{$normal['max']}h)",
                'severity' => 'warning',
            ];
        } elseif ($duration > $normal['max']) {
            $issues[] = [
                'type' => 'LONG_DURATION',
                'message' => "Durée anormalement longue pour {$type}: {$duration}h (normal: {$normal['min']}-{$normal['max']}h)",
                'severity' => 'warning',
            ];
        }

        return $issues;
    }

    /**
     * @param list<Activite> $allActivities
     * @return list<array<string, mixed>>
     */
    private function checkSequenceLogic(Activite $activity, array $allActivities): array
    {
        $issues = [];
        $type = $activity->getTypeActivite();
        $activityDate = $activity->getDateDebut();

        if (!$activityDate) {
            return $issues;
        }

        foreach ($allActivities as $other) {
            if ($other->getIdActivite() === $activity->getIdActivite()) {
                continue;
            }

            $otherType = $other->getTypeActivite();
            $otherDate = $other->getDateDebut();

            if (!$otherDate) {
                continue;
            }

            $daysDiff = $activityDate->diff($otherDate)->days;

            // RECOLTE après SEMIS en moins de 7 jours = anormal
            if ($type === 'RECOLTE' && $otherType === 'SEMIS' && $daysDiff < 7 && $otherDate < $activityDate) {
                $issues[] = [
                    'type' => 'ILLOGICAL_SEQUENCE',
                    'message' => "RECOLTE {$daysDiff} jours après SEMIS (trop rapide)",
                    'severity' => 'error',
                ];
            }

            // SEMIS après RECOLTE dans les 3 jours = suspect
            if ($type === 'SEMIS' && $otherType === 'RECOLTE' && $daysDiff < 3 && $otherDate < $activityDate) {
                $issues[] = [
                    'type' => 'SUSPECT_SEQUENCE',
                    'message' => "SEMIS seulement {$daysDiff} jours après RECOLTE",
                    'severity' => 'warning',
                ];
            }
        }

        return $issues;
    }

    /**
     * Génère des recommandations IA basées sur les anomalies
     *
     * @param list<array{activity: Activite, issues: list<array<string, mixed>>}> $anomalies
     * @return array<string, mixed>
     */
    public function generateRecommendations(array $anomalies, int $userId): array
    {
        if (empty($anomalies)) {
            return [
                'status' => 'ok',
                'message' => 'Aucune anomalie détectée. Vos activités semblent cohérentes! ✅',
                'recommendations' => [],
            ];
        }

        $prompt = $this->buildAnomalyPrompt($anomalies, $userId);

        try {
            $response = $this->geminiService->callGemini($prompt);
            return [
                'status' => 'anomalies_found',
                'message' => 'Anomalies détectées. Voici les recommandations IA:',
                'recommendations' => $response,
                'anomalies_count' => count($anomalies),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Erreur lors de la génération des recommandations IA',
                'error' => $e->getMessage(),
                'raw_anomalies' => $anomalies,
            ];
        }
    }

    /**
     * @param list<array{activity: Activite, issues: list<array<string, mixed>>}> $anomalies
     */
    private function buildAnomalyPrompt(array $anomalies, int $userId): string
    {
        $anomalyText = '';
        foreach ($anomalies as $anomaly) {
            $activity = $anomaly['activity'];
            $issues = $anomaly['issues'];
            
            $anomalyText .= "\n📌 Activité: {$activity->getTitre()}\n";
            $anomalyText .= "   Type: {$activity->getTypeActivite()}\n";
            $anomalyText .= "   Date: " . $activity->getDateDebut()?->format('d/m/Y H:i') . "\n";
            $start = $activity->getDateDebut();
            $end = $activity->getDateFin();
            $diffInterval = ($start !== null && $end !== null) ? $end->diff($start) : null;
            $totalHours   = $diffInterval ? (int) ($diffInterval->days * 24 + $diffInterval->h) : null;
            $anomalyText .= "   Durée estimée: " . ($totalHours ?? 'N/A') . "h\n";
            $anomalyText .= "   Coût: {$activity->getCoutEstime()} DT\n";
            $anomalyText .= "   Problèmes:\n";
            
            foreach ($issues as $issue) {
                $anomalyText .= "   ⚠️  " . $issue['message'] . " [" . ($issue['severity'] ?? 'normal') . "]\n";
            }
        }

        return <<<PROMPT
Tu es un EXPERT AGRONOME TUNISIEN avec 20 ans d'expérience. Tu dois analyser les anomalies détectées dans le planning agricole d'un fermier et fournir des recommandations PRATIQUES et ACTIONABLES.

CONTEXTE:
$anomalyText

INSTRUCTIONS:
1. Identifie les problèmes CRITIQUES qui impactent la production (coûts excessifs, durées irréalistes, séquences illogiques)
2. Liste les POINTS D'ATTENTION qui pourraient être améliorés (optimisation, risques faibles)
3. Fournis des ACTIONS CONCRÈTES et RÉALISABLES
4. Les recommandations doivent être NUMÉROTÉES et ORDONNÉES par priorité
5. Inclus des SEUILS RÉALISTES pour l'agriculture tunisienne
6. Propose des GESTES PRATIQUES (pas juste de la théorie)

EXEMPLE DE BON FORMAT:
- Au lieu de "Réduire les coûts", dis: "Réduire le coût de SEMIS de 30 DT à 20 DT en achetant les semences en gros"
- Au lieu de "Vérifier le timing", dis: "Décaler la RECOLTE du 15/5 au 25/5 (besoin de 10 jours min après SEMIS)"

Réponds UNIQUEMENT et STRICTEMENT en JSON valide (sans texte avant/après) avec cette structure:
{
    "anomalies_critique": ["Problème 1 - Impact: XXX", "Problème 2 - Impact: XXX"],
    "anomalies_mineures": ["Point 1 - Observation: XXX", "Point 2 - Observation: XXX"],
    "recommandations": ["1. Action concrète avec détails chiffrés", "2. Deuxième action..."],
    "resume": "Analyse synthétique: situation actuelle vs optimale + ratio impact/effort"
}

IMPORTANT: Chaque recommandation doit être APPLICABLE IMMÉDIATEMENT et MESURABLE.
PROMPT;
    }
}
