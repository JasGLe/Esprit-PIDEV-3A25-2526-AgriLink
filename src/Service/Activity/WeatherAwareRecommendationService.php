<?php

namespace App\Service\Activity;

use App\Entity\Activity\Activite;
use App\Entity\UserManagement\User;
use App\Repository\Activity\ActiviteRepository;
use App\Service\GeminiService;
use App\Service\OpenWeatherMapService;

class WeatherAwareRecommendationService
{
    public function __construct(
        private readonly ActiviteRepository $activiteRepository,
        private readonly OpenWeatherMapService $weatherService,
        private readonly GeminiService $geminiService,
    ) {
    }

    /**
     * Single Gemini call → both planning + weather sections in one round-trip.
     *
     * Each card now carries a `detail` field used by the front-end modal.
     *
     * @return array{
     *   status: string,
     *   total: int,
     *   weather_summary: string|null,
     *   planning: list<array{activity: string, type: string, date: string, severity: string, bullets: list<string>, detail: string}>,
     *   weather:  list<array{activity: string, type: string, date: string, severity: string, bullets: list<string>, detail: string}>
     * }
     */
    public function generateConciseWeatherAwareRecommendations(User $user, int $nextDays = 10): array
    {
        $userId = $user->getId();

        $today         = new \DateTime();
        $horizon       = (clone $today)->modify(sprintf('+%d days', $nextDays));
        $allActivities = $this->activiteRepository->findBetweenDates($today, $horizon);

        $userActivities = array_values(array_filter(
            $allActivities,
            static fn (Activite $a) => $a->getIdAgriculteur() === $userId
        ));

        if (empty($userActivities)) {
            return [
                'status'          => 'no_activities',
                'total'           => 0,
                'weather_summary' => null,
                'planning'        => [],
                'weather'         => [],
            ];
        }

        $city     = trim((string) ($user->getVille() ?? ''));
        $forecast = $city !== '' ? $this->weatherService->getForecastForCity($city) : [];

        try {
            // ONE call — both sections returned in a single JSON response
            $raw = $this->geminiService->callGemini(
                $this->buildCombinedPrompt($userActivities, $forecast, $nextDays)
            );

            return [
                'status'          => 'ok',
                'total'           => count($userActivities),
                'weather_summary' => $raw['weather_summary'] ?? null,
                'planning'        => $raw['planning'] ?? [],
                'weather'         => $raw['weather']  ?? [],
            ];
        } catch (\Throwable) {
            [$planningFallback, $weatherFallback] = $this->buildRuleBasedRecommendations($userActivities, $forecast);

            return [
                'status'          => 'fallback',
                'total'           => count($userActivities),
                'weather_summary' => $this->buildWeatherSummary($forecast),
                'planning'        => $planningFallback,
                'weather'         => $weatherFallback,
            ];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Single combined prompt
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param Activite[]                       $activities
     * @param array<int, array<string, mixed>> $forecast
     */
    private function buildCombinedPrompt(array $activities, array $forecast, int $nextDays): string
    {
        $list        = $this->formatActivitiesText($activities);
        $weatherText = $this->formatForecastText($forecast);

        return <<<PROMPT
Tu es un conseiller agricole expert. En UNE SEULE réponse JSON, génère à la fois les recommandations de PLANIFICATION et les recommandations MÉTÉO pour les activités agricoles suivantes.

ACTIVITÉS DES {$nextDays} PROCHAINS JOURS:
{$list}

PRÉVISIONS MÉTÉO:
{$weatherText}

═══════════════════════════════════════
SECTION A — PLANIFICATION
═══════════════════════════════════════
Objectifs : conflits de planning, priorités, coûts anormaux, durées irréalistes, enchaînements logiques.

Pour chaque activité :
- 2-3 bullets courts (max 20 mots, commencer par un verbe d'action)
- 1 champ "detail" : paragraphe de 3-4 phrases détaillées expliquant pourquoi et comment agir (pour un popup de détail)
- severity = "critical" | "warning" | "ok"

═══════════════════════════════════════
SECTION B — MÉTÉO
═══════════════════════════════════════
Règles obligatoires :
- Pluie → décaler irrigation
- Température > 35°C → travailler avant 8h/après 18h
- Vent > 5 m/s → interdire traitements phytosanitaires
- Humidité > 80% → risque fongique
- Froid < 5°C → protéger semis

Pour chaque activité :
- 2-3 bullets courts avec valeurs météo précises (ex: "38°C", "Vent 7 m/s")
- 1 champ "detail" : paragraphe de 3-4 phrases expliquant l'impact météo et les actions recommandées
- severity = "critical" | "warning" | "ok"

═══════════════════════════════════════
FORMAT JSON OBLIGATOIRE (rien d'autre) :
═══════════════════════════════════════
{
  "weather_summary": "Résumé météo global en 1 phrase",
  "planning": [
    {
      "activity": "nom exact",
      "type": "TYPE_ACTIVITE",
      "date": "dd/mm/yyyy",
      "severity": "ok|warning|critical",
      "bullets": ["Action 1", "Action 2"],
      "detail": "Explication détaillée en 3-4 phrases pour ce conseil de planification."
    }
  ],
  "weather": [
    {
      "activity": "nom exact",
      "type": "TYPE_ACTIVITE",
      "date": "dd/mm/yyyy",
      "severity": "ok|warning|critical",
      "bullets": ["Conseil météo 1", "Conseil météo 2"],
      "detail": "Explication détaillée en 3-4 phrases sur l'impact météo et les actions à prendre."
    }
  ]
}
PROMPT;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Rule-based fallback (no AI)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param  Activite[]                       $activities
     * @param  array<int, array<string, mixed>> $forecast
     * @return array{0: list<array<string,mixed>>, 1: list<array<string,mixed>>}
     */
    private function buildRuleBasedRecommendations(array $activities, array $forecast): array
    {
        $forecastByDate = [];
        foreach ($forecast as $day) {
            $forecastByDate[$day['dateKey'] ?? ''] = $day;
        }

        $planning = [];
        $weather  = [];

        foreach ($activities as $a) {
            $type      = $a->getTypeActivite();
            $dateStr   = $a->getDateDebut()?->format('Y-m-d') ?? '';
            $dateLabel = $a->getDateDebut()?->format('d/m/Y') ?? '?';
            $title     = $a->getTitre() ?? $type;
            $cost      = (float) ($a->getCoutEstime() ?? 0);
            $durationH = $this->totalHours($a->getDateDebut(), $a->getDateFin());
            $w         = $forecastByDate[$dateStr] ?? null;

            // ── Planning ──────────────────────────────────────────────────────
            $planBullets  = [];
            $planSeverity = 'ok';
            $planDetail   = '';

            $maxCosts     = ['SEMIS' => 100, 'IRRIGATION' => 150, 'RECOLTE' => 300, 'TRAITEMENT' => 200, 'TAILLE' => 100];
            $maxDurations = ['SEMIS' => 8, 'IRRIGATION' => 12, 'RECOLTE' => 16, 'TRAITEMENT' => 6, 'TAILLE' => 10];

            if ($cost > ($maxCosts[$type] ?? 500)) {
                $planBullets[] = sprintf('Coût de %.0f DT élevé pour %s — vérifier le devis.', $cost, $type);
                $planSeverity  = 'warning';
                $planDetail    = sprintf(
                    'Le coût estimé de %.0f DT pour cette activité de type %s dépasse les valeurs habituelles (maximum recommandé : %d DT). '
                    . 'Il est conseillé de revoir le devis avec votre fournisseur ou de comparer avec des prestataires alternatifs. '
                    . 'Un coût excessif peut peser sur la rentabilité globale de l\'exploitation pour cette période.',
                    $cost,
                    $type,
                    $maxCosts[$type] ?? 500
                );
            }

            if ($durationH > ($maxDurations[$type] ?? 24)) {
                $planBullets[] = sprintf('Durée de %dh longue — envisager 2 sessions.', $durationH);
                $planSeverity  = $planSeverity === 'ok' ? 'warning' : $planSeverity;
                $planDetail   .= sprintf(
                    ' La durée prévue de %dh pour "%s" est supérieure à la normale (%dh max). '
                    . 'Diviser cette activité en deux sessions distinctes permet de maintenir la qualité du travail et d\'éviter la fatigue des équipes.',
                    $durationH,
                    $title,
                    $maxDurations[$type] ?? 24
                );
            }

            if (empty($planBullets)) {
                $planBullets[] = match ($type) {
                    'SEMIS'      => 'Vérifier la disponibilité des semences et outils avant le jour J.',
                    'IRRIGATION' => 'Contrôler le débit et la pression du système avant démarrage.',
                    'RECOLTE'    => 'Préparer les contenants et vérifier la maturité 24h avant.',
                    'TRAITEMENT' => 'Confirmer la disponibilité des produits phytosanitaires.',
                    'TAILLE'     => 'Désinfecter les outils et préparer les pansements végétaux.',
                    default      => 'Vérifier la disponibilité des ressources humaines et matérielles.',
                };
                $planDetail = match ($type) {
                    'SEMIS'      => 'Avant le semis, assurez-vous que les semences sont de qualité certifiée et stockées dans de bonnes conditions. Vérifiez l\'humidité du sol et la disponibilité de l\'équipement. Un semis réalisé dans de bonnes conditions augmente significativement le taux de germination.',
                    'IRRIGATION' => 'Avant de démarrer l\'irrigation, testez le débit et la pression de chaque rampe ou goutteur. Une vérification préalable évite les pertes d\'eau et garantit une distribution homogène. Planifiez l\'irrigation tôt le matin pour réduire l\'évaporation.',
                    'RECOLTE'    => 'Préparez les caisses, filets ou sacs de stockage la veille. Vérifiez la maturité par observation visuelle et tests de dureté. Organisez le transport vers le point de stockage pour minimiser le temps entre récolte et conservation.',
                    'TRAITEMENT' => 'Vérifiez les stocks de produits phytosanitaires et les dates de péremption. Préparez les équipements de protection individuelle (masque, combinaison, gants). Respectez les délais de réentrée et les doses homologuées.',
                    'TAILLE'     => 'Désinfectez sécateurs et scies à l\'alcool ou eau de Javel diluée entre chaque plant pour éviter la propagation de maladies. Appliquez un mastic cicatrisant sur les coupes importantes. Réalisez la taille par temps sec pour réduire les infections fongiques.',
                    default      => 'Vérifiez la disponibilité des ressources humaines et du matériel nécessaire. Planifiez un créneau de travail adapté aux conditions climatiques prévues. Assurez-vous que toutes les parties prenantes sont informées de la date et des objectifs.',
                };
            }

            $planning[] = [
                'activity' => $title,
                'type'     => $type,
                'date'     => $dateLabel,
                'severity' => $planSeverity,
                'bullets'  => $planBullets,
                'detail'   => trim($planDetail),
            ];

            // ── Weather ───────────────────────────────────────────────────────
            $wBullets  = [];
            $wSeverity = 'ok';
            $wDetail   = '';

            if ($w !== null) {
                $condition = strtolower((string) ($w['condition'] ?? ''));
                $temp      = (float) ($w['tempMax'] ?? 0);
                $wind      = (float) ($w['windSpeed'] ?? 0);
                $humidity  = (int) ($w['humidity'] ?? 0);
                $isRainy   = str_contains($condition, 'rain') || str_contains($condition, 'drizzle');
                $isHot     = $temp > 35;
                $isWindy   = $wind > 5;
                $isHumid   = $humidity > 80;

                if ($type === 'IRRIGATION' && $isRainy) {
                    $wBullets[] = 'Pluie prévue — décaler cette irrigation au lendemain.';
                    $wSeverity  = 'warning';
                    $wDetail    = 'Des précipitations sont attendues le jour de votre irrigation planifiée. Décaler l\'irrigation d\'au moins 24h après la fin des pluies permet d\'éviter le gaspillage d\'eau et la saturation du sol. Surveillez les prévisions pour choisir le meilleur moment de reprise.';
                }
                if ($type === 'TRAITEMENT' && $isWindy) {
                    $wBullets[] = sprintf('Vent à %.1f m/s — annuler le traitement, risque de dérive.', $wind);
                    $wSeverity  = 'critical';
                    $wDetail    = sprintf(
                        'Le vent prévu à %.1f m/s le jour du traitement dépasse le seuil réglementaire de 5 m/s. '
                        . 'Appliquer des produits phytosanitaires par vent fort entraîne une dérive pouvant contaminer les cultures voisines, les cours d\'eau et présenter un risque sanitaire pour l\'opérateur. '
                        . 'Reportez impérativement ce traitement à un jour de vent inférieur à 3 m/s, de préférence tôt le matin.',
                        $wind
                    );
                }
                if ($isHot && in_array($type, ['SEMIS', 'IRRIGATION', 'TAILLE', 'TRAITEMENT'], true)) {
                    $wBullets[] = sprintf('%.0f°C attendus — travailler avant 8h ou après 18h.', $temp);
                    $wSeverity  = $wSeverity === 'ok' ? 'warning' : $wSeverity;
                    $wDetail   .= sprintf(
                        ' Les températures prévues à %.0f°C le %s représentent un risque de stress thermique pour les cultures et les équipes. '
                        . 'Privilégiez les créneaux matinaux (avant 8h) ou en fin de journée (après 18h) pour réaliser "%s". '
                        . 'Augmentez la fréquence d\'arrosage et vérifiez l\'état hydrique des plantes en milieu de journée.',
                        $temp,
                        $dateLabel,
                        $title
                    );
                }
                if ($isHumid && in_array($type, ['RECOLTE', 'SEMIS'], true)) {
                    $wBullets[] = sprintf('Humidité à %d%% — surveiller les risques fongiques.', $humidity);
                    $wSeverity  = $wSeverity === 'ok' ? 'warning' : $wSeverity;
                    $wDetail   .= sprintf(
                        ' Une humidité relative de %d%% favorise le développement de champignons pathogènes (Botrytis, mildiou…). '
                        . 'Après la récolte ou le semis, veillez à assurer une bonne aération et évitez de laisser de la végétation mouillée en contact prolongé avec le sol. '
                        . 'Envisagez un traitement préventif fongique si cette humidité persiste plusieurs jours.',
                        $humidity
                    );
                }
            }

            if (empty($wBullets)) {
                $wBullets[] = 'Conditions météo favorables — aucune contrainte identifiée.';
                $wDetail    = sprintf(
                    'Les conditions météorologiques prévues le %s sont favorables pour la réalisation de "%s". '
                    . 'Profitez de cette fenêtre météo pour avancer sur cette activité dans de bonnes conditions. '
                    . 'Restez attentif aux évolutions des prévisions à 48h pour anticiper tout changement.',
                    $dateLabel,
                    $title
                );
            }

            $weather[] = [
                'activity' => $title,
                'type'     => $type,
                'date'     => $dateLabel,
                'severity' => $wSeverity,
                'bullets'  => $wBullets,
                'detail'   => trim($wDetail),
            ];
        }

        return [$planning, $weather];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /** @param Activite[] $activities */
    private function formatActivitiesText(array $activities): string
    {
        $text = '';
        foreach ($activities as $a) {
            $text .= sprintf(
                "- [%s] \"%s\" le %s (coût: %s DT, durée: %sh, statut: %s)\n",
                $a->getTypeActivite(),
                $a->getTitre() ?? $a->getTypeActivite(),
                $a->getDateDebut()?->format('d/m/Y') ?? '?',
                $a->getCoutEstime() ?? '?',
                $this->totalHours($a->getDateDebut(), $a->getDateFin()) ?? '?',
                $a->getStatut() ?? '?'
            );
        }

        return $text;
    }

    /** @param array<int, array<string, mixed>> $forecast */
    private function formatForecastText(array $forecast): string
    {
        if (empty($forecast)) {
            return "Données météo non disponibles.\n";
        }

        $text = '';
        foreach ($forecast as $day) {
            $text .= sprintf(
                "- %s: %s, %.0f–%.0f°C, vent %.1f m/s, humidité %d%%\n",
                $day['dateLabel'],
                $day['description'],
                $day['tempMin'],
                $day['tempMax'],
                $day['windSpeed'],
                $day['humidity']
            );
        }

        return $text;
    }

    /**
     * Total duration in hours between two dates.
     * Uses days*24+h to avoid the bug where DateInterval->h only returns
     * the remainder hours (e.g. 2 years 22h → h=22, not 17542).
     */
    private function totalHours(?\DateTimeInterface $start, ?\DateTimeInterface $end): int
    {
        if ($start === null || $end === null) {
            return 0;
        }
        $diff = $end instanceof \DateTime
            ? $end->diff($start)
            : \DateTime::createFromInterface($end)->diff(\DateTime::createFromInterface($start));

        return (int) ($diff->days * 24 + $diff->h);
    }

    /** @param array<int, array<string, mixed>> $forecast */
    private function buildWeatherSummary(array $forecast): ?string
    {
        if (empty($forecast)) {
            return null;
        }

        $conditions = array_unique(array_map(
            static fn (array $d) => (string) ($d['description'] ?? ''),
            array_slice($forecast, 0, 3)
        ));

        return 'Prévisions : ' . implode(', ', $conditions) . '.';
    }
}
