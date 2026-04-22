<?php

namespace App\Controller\Equipment;

use App\Entity\Equipement;
use App\Repository\MaintenanceRepository;
use App\Service\Equipment\GroqDiagnosticService;
use App\Service\Equipment\GeminiVisionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * DiagnosticController
 * ─────────────────────────────────────────────────────────────────────────────
 * Expose deux endpoints AJAX pour le diagnostic IA des équipements agricoles.
 *
 * Endpoints :
 *  POST /equipement/{id}/diagnostic-ia
 *    → Diagnostic textuel via GroqDiagnosticService (LLaMA 3.1 8B Instant).
 *    → Reçoit les données structurées de l'équipement + ses 10 dernières
 *      maintenances et retourne un JSON avec : indice de santé, score,
 *      résumé, analyse par critère, alertes, recommandations, prochaine
 *      maintenance estimée.
 *
 *  POST /equipement/{id}/analyse-photo
 *    → Analyse visuelle via GeminiVisionService (LLaMA 4 Scout multimodal).
 *    → Reçoit une image multipart/form-data (champ "photo", max 5 MB),
 *      la convertit en base64, et retourne un JSON avec : état visuel,
 *      score visuel, anomalies détectées, zones problématiques,
 *      recommandations visuelles, conclusion.
 *
 * Sécurité (commune aux deux endpoints) :
 *  - Vérification que l'équipement appartient à l'utilisateur connecté (userlog).
 *  - Token CSRF dans le header X-CSRF-Token (endpoint diagnostic-ia uniquement).
 *  - Taille et type MIME vérifiés côté serveur pour les photos.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DiagnosticController extends AbstractController
{
    /**
     * @param GroqDiagnosticService $groqService         Service de diagnostic textuel IA (Groq LLaMA)
     * @param GeminiVisionService   $geminiVisionService Service d'analyse visuelle IA (Groq multimodal)
     * @param MaintenanceRepository $maintenanceRepo     Pour charger l'historique des maintenances
     */
    public function __construct(
        private readonly GroqDiagnosticService $groqService,
        private readonly GeminiVisionService   $geminiVisionService,
        private readonly MaintenanceRepository $maintenanceRepo
    ) {}

    /**
     * Endpoint AJAX — Diagnostic IA textuel d'un équipement.
     *
     * Fonctionnement :
     *  1. Vérifie que l'équipement appartient à l'utilisateur connecté.
     *  2. Vérifie le token CSRF (header X-CSRF-Token: diagnostic_ia_{id}).
     *  3. Calcule l'âge de l'équipement et les jours depuis la dernière maintenance.
     *  4. Structure toutes les données de l'équipement dans un tableau.
     *  5. Charge les 10 dernières maintenances.
     *  6. Appelle GroqDiagnosticService::diagnostiquer() et retourne le JSON.
     *
     * Réponse JSON en cas de succès :
     * {
     *   "success": true,
     *   "diagnostic": {
     *     "indice_sante": "Excellent|Bon|Attention|Critique",
     *     "score": 0-100,
     *     "resume": "...",
     *     "analyse_par_critere": { ... },
     *     "alertes": [...],
     *     "recommandations": [...],
     *     "prochaine_maintenance_estimee": "Dans X jours|mois"
     *   }
     * }
     *
     * @param Equipement $equipement  Entité résolue via ParamConverter (id dans l'URL)
     * @param Request    $request     Pour lire le header X-CSRF-Token
     *
     * @return JsonResponse  200 avec diagnostic, ou 403 si accès refusé, ou 500 si erreur IA
     */
    #[Route('/equipement/{id}/diagnostic-ia', name: 'equipement_diagnostic_ia', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function diagnostiquer(Equipement $equipement, Request $request): JsonResponse
    {
        // ── 1. Vérification ownership ──────────────────────────────────────────
        // L'agriculteur ne peut diagnostiquer QUE ses propres équipements
        if ($equipement->getUserlog() !== $this->getUser()?->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        // ── 2. Vérification token CSRF (sécurité AJAX) ─────────────────────────
        $token = $request->headers->get('X-CSRF-Token', '');
        if (!$this->isCsrfTokenValid('diagnostic_ia_' . $equipement->getId(), $token)) {
            return $this->json(['error' => 'Token de sécurité invalide.'], 403);
        }

        // ── 3. Calcul de l'âge de l'équipement (années depuis acquisition) ─────
        $ageAnnees = null;
        if ($equipement->getDateAcquisition()) {
            $ageAnnees = (int) $equipement->getDateAcquisition()
                ->diff(new \DateTimeImmutable())
                ->format('%y');
        }

        // ── 4. Calcul des jours depuis la dernière maintenance ─────────────────
        $joursDepuisMaintenance = null;
        if ($equipement->getDateDerniereMaintenance()) {
            // %a = nombre total de jours (différent de %d qui donne les jours restants du mois)
            $joursDepuisMaintenance = (int) $equipement->getDateDerniereMaintenance()
                ->diff(new \DateTimeImmutable())
                ->format('%a');
        }

        // ── 5. Structurer toutes les données de l'équipement pour le service ───
        // Toutes les données sont transmises — le service n'inclut dans le prompt
        // que celles qui sont non-null pour ne pas surcharger le LLM
        $equipementData = [
            // Informations générales
            'categorie'   => $equipement->getCategorie(),
            'nom'         => $equipement->getNom(),
            'type'        => $equipement->getType(),
            'marque'      => $equipement->getMarque(),
            'modele'      => $equipement->getModele(),
            'statut'      => $equipement->getStatut(),
            'description' => $equipement->getDescription(),
            'isVehicule'  => $equipement->isVehicule(),

            // Âge
            'dateAcquisition' => $equipement->getDateAcquisition()?->format('d/m/Y'),
            'ageAnnees'       => $ageAnnees,

            // Données véhicule (null si catégorie non motorisée)
            'kilometrageActuel'               => $equipement->getKilometrageActuel(),
            'seuilKmMaintenance'              => $equipement->getSeuilKmMaintenance(),
            'kilometrageDerniereMaintenance'  => $equipement->getKilometrageDerniereMaintenance(),
            'heuresUtilisation'               => $equipement->getHeuresUtilisation(),
            'seuilHeuresMaintenance'          => $equipement->getSeuilHeuresMaintenance(),
            'heuresDerniereMaintenance'       => $equipement->getHeuresDerniereMaintenance(),

            // Maintenance périodique basée sur les jours
            'seuilJoursMaintenance'      => $equipement->getSeuilJoursMaintenance(),
            'dateDerniereMaintenance'    => $equipement->getDateDerniereMaintenance()?->format('d/m/Y'),
            'joursDepuisMaintenance'     => $joursDepuisMaintenance,
        ];

        // ── 6. Récupérer les 10 dernières maintenances de cet équipement ────────
        $maintenances = $this->maintenanceRepo->findBy(
            ['equipementId' => $equipement->getId()],
            ['datePlanifiee' => 'DESC'],
            10
        );

        // ── 7. Appel au service IA Groq (LLaMA 3.1) ────────────────────────────
        try {
            $diagnostic = $this->groqService->diagnostiquer($equipementData, $maintenances);
            return $this->json(['success' => true, 'diagnostic' => $diagnostic]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error'   => 'Erreur lors du diagnostic IA : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint AJAX — Analyse visuelle d'un équipement via photo.
     *
     * Fonctionnement :
     *  1. Vérifie que l'équipement appartient à l'utilisateur connecté.
     *  2. Récupère le fichier "photo" du multipart/form-data.
     *  3. Valide : présence, taille ≤ 5 MB, type MIME (jpg/png/webp uniquement).
     *  4. Lit le contenu binaire et l'encode en base64.
     *  5. Appelle GeminiVisionService::analyserPhoto() avec l'image et les infos
     *     de l'équipement pour enrichir le prompt.
     *  6. Retourne le résultat JSON de l'analyse visuelle.
     *
     * Réponse JSON en cas de succès :
     * {
     *   "success": true,
     *   "analyse": {
     *     "etat_visuel": "Bon|Usure normale|Dégradation|Critique",
     *     "score_visuel": 0-100,
     *     "anomalies_detectees": [...],
     *     "zones_problematiques": [...],
     *     "recommandations_visuelles": [...],
     *     "conclusion": "..."
     *   }
     * }
     *
     * @param Equipement $equipement  Entité résolue via ParamConverter (id dans l'URL)
     * @param Request    $request     Pour accéder au fichier uploadé (request->files->get('photo'))
     *
     * @return JsonResponse  200 avec analyse, ou 400 si photo invalide, ou 403/500 si erreur
     */
    #[Route('/equipement/{id}/analyse-photo', name: 'equipement_analyse_photo', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function analyserPhoto(Equipement $equipement, Request $request): JsonResponse
    {
        // ── 1. Vérification ownership ──────────────────────────────────────────
        if ($equipement->getUserlog() !== $this->getUser()?->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        // ── 2. Récupérer le fichier uploadé (champ "photo") ───────────────────
        $file = $request->files->get('photo');

        if (!$file) {
            return $this->json(['success' => false, 'error' => 'Aucune photo reçue.'], 400);
        }

        // ── 3. Valider la taille (max 5 MB) ───────────────────────────────────
        if ($file->getSize() > 5 * 1024 * 1024) {
            return $this->json(['success' => false, 'error' => 'La photo dépasse la taille maximale autorisée (5 MB).'], 400);
        }

        // ── 4. Valider le type MIME (image uniquement) ─────────────────────────
        $mimeType     = $file->getMimeType();
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mimeType, $allowedMimes, true)) {
            return $this->json(['success' => false, 'error' => 'Format non supporté. Utilisez JPG, PNG ou WEBP.'], 400);
        }

        // ── 5. Convertir l'image en base64 côté serveur ────────────────────────
        // Le LLM reçoit l'image en data URI (data:image/jpeg;base64,...)
        $imageContent = file_get_contents($file->getPathname());

        if ($imageContent === false) {
            return $this->json(['success' => false, 'error' => 'Impossible de lire le fichier uploadé.'], 500);
        }

        $base64Image = base64_encode($imageContent);

        // ── 6. Appel au service Gemini Vision (LLaMA 4 Scout multimodal) ──────
        try {
            $analyse = $this->geminiVisionService->analyserPhoto($base64Image, $mimeType, $equipement);
            return $this->json(['success' => true, 'analyse' => $analyse]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error'   => 'Erreur lors de l\'analyse visuelle : ' . $e->getMessage(),
            ], 500);
        }
    }
}
