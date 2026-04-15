<?php

namespace App\Controller\Equipment;

use App\Entity\Equipement;
use App\Repository\MaintenanceRepository;
use App\Service\Equipment\GroqDiagnosticService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * DiagnosticController
 * ─────────────────────────────────────────────────────────────────────────────
 * Expose un endpoint AJAX POST qui reçoit l'id d'un équipement,
 * collecte toutes ses données, appelle GroqDiagnosticService,
 * et retourne le diagnostic JSON au navigateur.
 *
 * Route : POST /equipement/{id}/diagnostic-ia
 * Name  : equipement_diagnostic_ia
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DiagnosticController extends AbstractController
{
    public function __construct(
        private readonly GroqDiagnosticService $groqService,
        private readonly MaintenanceRepository $maintenanceRepo
    ) {}

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

        // ── 3. Calcul de l'âge de l'équipement ────────────────────────────────
        $ageAnnees = null;
        if ($equipement->getDateAcquisition()) {
            $ageAnnees = (int) $equipement->getDateAcquisition()
                ->diff(new \DateTimeImmutable())
                ->format('%y');
        }

        // ── 4. Calcul des jours depuis la dernière maintenance ─────────────────
        $joursDepuisMaintenance = null;
        if ($equipement->getDateDerniereMaintenance()) {
            $joursDepuisMaintenance = (int) $equipement->getDateDerniereMaintenance()
                ->diff(new \DateTimeImmutable())
                ->format('%a'); // %a = nombre total de jours (pas %d)
        }

        // ── 5. Structurer les données de l'équipement pour le service ──────────
        // Toutes les données disponibles sont transmises — le service construira
        // le prompt en incluant uniquement celles qui sont non-null
        $equipementData = [
            // Infos générales
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

            // Données véhicule (null si pas véhicule)
            'kilometrageActuel'               => $equipement->getKilometrageActuel(),
            'seuilKmMaintenance'              => $equipement->getSeuilKmMaintenance(),
            'kilometrageDerniereMaintenance'  => $equipement->getKilometrageDerniereMaintenance(),
            'heuresUtilisation'               => $equipement->getHeuresUtilisation(),
            'seuilHeuresMaintenance'          => $equipement->getSeuilHeuresMaintenance(),
            'heuresDerniereMaintenance'       => $equipement->getHeuresDerniereMaintenance(),

            // Maintenance périodique
            'seuilJoursMaintenance'      => $equipement->getSeuilJoursMaintenance(),
            'dateDerniereMaintenance'    => $equipement->getDateDerniereMaintenance()?->format('d/m/Y'),
            'joursDepuisMaintenance'     => $joursDepuisMaintenance,
        ];

        // ── 6. Récupérer les 10 dernières maintenances de cet équipement ───────
        $maintenances = $this->maintenanceRepo->findBy(
            ['equipementId' => $equipement->getId()],
            ['datePlanifiee' => 'DESC'],
            10
        );

        // ── 7. Appel au service IA Groq ────────────────────────────────────────
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
}
