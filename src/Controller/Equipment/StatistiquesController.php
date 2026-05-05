<?php

namespace App\Controller\Equipment;

use App\Repository\EquipementRepository;
use App\Repository\MaintenanceRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * StatistiquesController
 * ─────────────────────────────────────────────────────────────────────────────
 * Génère les statistiques agrégées du parc d'équipements et des maintenances
 * de l'agriculteur connecté, pour affichage dans des graphiques (Chart.js)
 * et export en rapport PDF (Dompdf).
 *
 * Données calculées :
 *  - Équipements : répartition par statut, par catégorie, par type.
 *  - Maintenances : répartition par statut, par type, évolution sur 12 mois,
 *    nombre de maintenances en retard.
 *
 * Les deux actions (index et pdf) partagent le même calcul via buildStats()
 * pour garantir la cohérence entre la vue web et le rapport téléchargé.
 *
 * Route prefix : /equipement/statistiques  (name prefix : equipement_stats_)
 * ─────────────────────────────────────────────────────────────────────────────
 */
#[Route('/equipement/statistiques', name: 'equipement_stats_')]
class StatistiquesController extends AbstractController
{
    /**
     * Tableau de bord statistique (vue web avec graphiques Chart.js).
     *
     * Récupère les équipements et maintenances de l'utilisateur connecté,
     * calcule toutes les statistiques via buildStats(), et les passe à la vue
     * qui les utilise pour générer des graphiques circulaires, barres et courbes.
     *
     * @param EquipementRepository  $equipRepo  Accès aux équipements en base
     * @param MaintenanceRepository $mainRepo   Accès aux maintenances en base
     *
     * @return Response  Vue equipment/statistiques/index.html.twig
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        EquipementRepository  $equipRepo,
        MaintenanceRepository $mainRepo
    ): Response {
        /** @var \App\Entity\User $user */ // Fix PHPStan — getUser() retourne UserInterface|null, pas App\Entity\User
        $user          = $this->getUser();
        $userId        = $user->getId();
        $equipements   = $equipRepo->findBy(['userlog' => $userId]);
        $equipementIds = array_map(fn($e) => $e->getId(), $equipements);

        $stats = $this->buildStats($userId, $equipementIds, $equipRepo, $mainRepo);

        return $this->render('equipment/statistiques/index.html.twig', $stats);
    }

    /**
     * Export du rapport statistique en PDF (téléchargement direct).
     *
     * Utilise le même calcul que index() via buildStats(), mais rend la vue
     * equipment/statistiques/pdf.html.twig (sans CSS Bootstrap, optimisée Dompdf).
     * Le fichier est nommé automatiquement avec la date du jour.
     *
     * Configuration Dompdf :
     *  - Police : DejaVu Sans (supporte les caractères UTF-8 et accents français)
     *  - Parser : HTML5
     *  - Format : A4 portrait
     *
     * @param EquipementRepository  $equipRepo  Accès aux équipements en base
     * @param MaintenanceRepository $mainRepo   Accès aux maintenances en base
     *
     * @return Response  Réponse binaire PDF avec Content-Disposition: attachment
     */
    #[Route('/pdf', name: 'pdf', methods: ['GET'])]
    public function pdf(
        EquipementRepository  $equipRepo,
        MaintenanceRepository $mainRepo
    ): Response {
        /** @var \App\Entity\User $user */ // Fix PHPStan — getUser() retourne UserInterface|null, pas App\Entity\User
        $user          = $this->getUser();
        $userId        = $user->getId();
        $equipements   = $equipRepo->findBy(['userlog' => $userId]);
        $equipementIds = array_map(fn($e) => $e->getId(), $equipements);

        $stats = $this->buildStats($userId, $equipementIds, $equipRepo, $mainRepo);

        // ── Rendu HTML de la vue PDF (sans JS, sans ressources externes) ─────
        $html = $this->renderView('equipment/statistiques/pdf.html.twig', $stats);

        // ── Configuration Dompdf ─────────────────────────────────────────────
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');   // supporte UTF-8 et accents
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // ── Nom du fichier avec la date du jour ──────────────────────────────
        $filename = 'rapport-equipements-' . date('Y-m-d') . '.pdf';

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    /**
     * Calcule et structure toutes les statistiques pour un utilisateur.
     *
     * Centralise le calcul commun aux actions index() et pdf() pour éviter
     * la duplication. Retourne un tableau associatif directement passable
     * à $this->render() comme paramètres Twig.
     *
     * Si l'utilisateur n'a aucun équipement, les statistiques de maintenance
     * retournent des tableaux vides / zéros (pas de requête en base inutile).
     *
     * Clés du tableau retourné :
     *  - totalEquipements            : int — nombre d'équipements de l'utilisateur
     *  - equipParStatut              : array{statut => count}
     *  - equipParStatutLabels/Valeurs: tableau séparé pour Chart.js
     *  - equipParCategorie           : array{categorie => count}
     *  - equipParCategorieLabels/Valeurs
     *  - equipParType                : array{type => count}
     *  - equipParTypeLabels/Valeurs
     *  - totalMaintenances           : int — somme de toutes les maintenances
     *  - mainParStatut               : array{statut => count}
     *  - mainParStatutLabels/Valeurs
     *  - mainParType                 : array{type => count}
     *  - mainParTypeLabels/Valeurs
     *  - mainParMois                 : array{'Y-m' => count} — 12 derniers mois
     *  - mainParMoisLabels/Valeurs
     *  - mainEnRetard                : int — maintenances actives avec date dépassée
     *
     * @param int                   $userId        ID de l'utilisateur connecté
     * @param int[]                 $equipementIds IDs de ses équipements
     * @param EquipementRepository  $equipRepo     Repository équipements
     * @param MaintenanceRepository $mainRepo      Repository maintenances
     *
     * @return array  Tableau de statistiques prêt à passer à Twig
     */
    private function buildStats(
        int $userId,
        array $equipementIds,
        EquipementRepository $equipRepo,
        MaintenanceRepository $mainRepo
    ): array {
        // ── Statistiques équipements ─────────────────────────────────────────
        $equipParStatut    = $equipRepo->countByStatut($userId);
        $equipParCategorie = $equipRepo->countByCategorie($userId);
        $equipParType      = $equipRepo->countByType($userId);

        // ── Statistiques maintenances (retournent [] / 0 si aucun équipement) ─
        $mainParStatut = empty($equipementIds) ? [] : $mainRepo->countByStatutForUser($equipementIds);
        $mainParType   = empty($equipementIds) ? [] : $mainRepo->countByTypeForUser($equipementIds);
        $mainParMois   = empty($equipementIds) ? [] : $mainRepo->countByMoisForUser($equipementIds);
        $mainEnRetard  = empty($equipementIds) ? 0  : $mainRepo->countEnRetardForUser($equipementIds);

        return [
            'totalEquipements'         => count($equipementIds),
            // Équipements par statut
            'equipParStatut'           => $equipParStatut,
            'equipParStatutLabels'     => array_keys($equipParStatut),
            'equipParStatutValeurs'    => array_values($equipParStatut),
            // Équipements par catégorie
            'equipParCategorie'        => $equipParCategorie,
            'equipParCategorieLabels'  => array_keys($equipParCategorie),
            'equipParCategorieValeurs' => array_values($equipParCategorie),
            // Équipements par type
            'equipParType'             => $equipParType,
            'equipParTypeLabels'       => array_keys($equipParType),
            'equipParTypeValeurs'      => array_values($equipParType),
            // Maintenances globales
            'totalMaintenances'        => array_sum($mainParStatut),
            // Maintenances par statut
            'mainParStatut'            => $mainParStatut,
            'mainParStatutLabels'      => array_keys($mainParStatut),
            'mainParStatutValeurs'     => array_values($mainParStatut),
            // Maintenances par type (Préventive / Corrective)
            'mainParType'              => $mainParType,
            'mainParTypeLabels'        => array_keys($mainParType),
            'mainParTypeValeurs'       => array_values($mainParType),
            // Évolution mensuelle sur les 12 derniers mois
            'mainParMois'              => $mainParMois,
            'mainParMoisLabels'        => array_keys($mainParMois),
            'mainParMoisValeurs'       => array_values($mainParMois),
            // KPI retard
            'mainEnRetard'             => $mainEnRetard,
        ];
    }
}
