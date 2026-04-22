<?php

namespace App\Controller\Equipment;

use App\Entity\Equipement;
use App\Repository\EquipementRepository;
use App\Repository\MaintenanceRepository;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * PasseportController
 * ─────────────────────────────────────────────────────────────────────────────
 * Gère le passeport numérique de chaque équipement agricole.
 *
 * Un passeport numérique est une fiche publique (accessible sans authentification
 * via QR code) qui regroupe : identité technique, statut, indicateur de santé,
 * et historique des 3 dernières maintenances.
 *
 * Fonctionnalités :
 *  - Génération du QR code SVG (BaconQrCode) pointant vers la fiche publique.
 *  - Génération automatique du code passeport (EQUIP-XXXXXX) si absent.
 *  - Calcul de l'indicateur de santé basé sur le statut et les retards de maintenance.
 *  - Accès par ID numérique (depuis l'interface privée) ou par code passeport (depuis QR).
 *
 * Deux modes d'accès :
 *  - /passeport/voir/{id}   : accès authentifié depuis la fiche équipement
 *  - /passeport/{code}      : accès public via QR code scanné
 *
 * Route prefix : /passeport  (name prefix : passeport_)
 * ─────────────────────────────────────────────────────────────────────────────
 */
#[Route('/passeport', name: 'passeport_')]
class PasseportController extends AbstractController
{
    /**
     * Génère le QR code SVG pour un équipement.
     *
     * Si l'équipement n'a pas encore de code passeport, en génère un automatiquement
     * (format EQUIP-XXXXXX via Equipement::genererCodePasseport()) et le persiste.
     *
     * L'URL encodée dans le QR code utilise en priorité APP_PUBLIC_URL (ngrok ou URL
     * de production) plutôt que le host local, pour que le QR soit scannable depuis
     * un téléphone extérieur au réseau local.
     *
     * @param Equipement           $equipement  Équipement cible (ParamConverter)
     * @param EntityManagerInterface $em        Pour persister le code passeport si nouveau
     * @param Request              $request     Pour obtenir le host actuel en fallback
     *
     * @return Response  Image SVG du QR code (Content-Type: image/svg+xml)
     */
    #[Route('/qr/{id}', name: 'qr', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function qr(
        Equipement $equipement,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        // ── Générer et sauvegarder le code passeport si absent ───────────────
        if (!$equipement->getCodePasseport()) {
            $equipement->setCodePasseport($equipement->genererCodePasseport());
            $em->flush();
        }

        // ── Construire l'URL publique du passeport ───────────────────────────
        // APP_PUBLIC_URL (ex: https://xxx.ngrok-free.dev) est prioritaire sur le host
        // local pour que le QR soit scannable depuis mobile hors réseau local
        $baseUrl = $_ENV['APP_PUBLIC_URL'] ?? $request->getSchemeAndHttpHost();

        $url = $baseUrl . $this->generateUrl(
            'passeport_show_by_id',
            ['id' => $equipement->getId()]
        );

        // ── Génération du QR code SVG (300x300 px) ───────────────────────────
        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svg    = $writer->writeString($url);

        return new Response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    /**
     * Fiche passeport accessible par ID numérique (depuis l'interface privée).
     *
     * Utilisé quand l'agriculteur clique sur "Voir le passeport" depuis la fiche
     * équipement — l'accès est par ID et non par code pour éviter de devoir
     * récupérer le code d'abord.
     *
     * Génère le code passeport si absent, charge les 3 dernières maintenances,
     * calcule l'indicateur de santé, et génère l'URL absolue du QR code à afficher.
     *
     * @param int                   $id        ID numérique de l'équipement
     * @param EquipementRepository  $equipRepo Pour charger l'équipement
     * @param MaintenanceRepository $mainRepo  Pour charger les 3 dernières maintenances
     * @param EntityManagerInterface $em       Pour persister le code passeport si nouveau
     *
     * @return Response  Vue equipment/passeport/show.html.twig ou 404 si introuvable
     */
    #[Route('/voir/{id}', name: 'show_by_id', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showById(
        int $id,
        EquipementRepository $equipRepo,
        MaintenanceRepository $mainRepo,
        EntityManagerInterface $em
    ): Response {
        $equipement = $equipRepo->find($id);

        if (!$equipement) {
            throw $this->createNotFoundException('Équipement introuvable.');
        }

        // ── Générer le code passeport si absent ─────────────────────────────
        if (!$equipement->getCodePasseport()) {
            $equipement->setCodePasseport($equipement->genererCodePasseport());
            $em->flush();
        }

        // ── 3 dernières maintenances (pour l'historique du passeport) ────────
        $maintenances = $mainRepo->findBy(
            ['equipementId' => $equipement->getId()],
            ['datePlanifiee' => 'DESC'],
            3
        );

        $sante  = $this->calculerSante($equipement, $maintenances);
        // URL absolue du QR code pour l'afficher comme <img src="...">
        $qrUrl  = $this->generateUrl(
            'passeport_qr',
            ['id' => $equipement->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->render('equipment/passeport/show.html.twig', [
            'equipement'   => $equipement,
            'maintenances' => $maintenances,
            'sante'        => $sante,
            'qrUrl'        => $qrUrl,
        ]);
    }

    /**
     * Fiche passeport accessible par code passeport (depuis QR code scanné).
     *
     * Route publique — ne requiert pas d'authentification. Scannable par n'importe
     * qui en possession du QR code (sur l'équipement physique, sur une facture, etc.).
     * Retourne une 404 si le code n'existe pas en base.
     *
     * @param string                $code      Code passeport (format EQUIP-XXXXXX)
     * @param EquipementRepository  $equipRepo Pour charger l'équipement par code
     * @param MaintenanceRepository $mainRepo  Pour charger les 3 dernières maintenances
     *
     * @return Response  Vue equipment/passeport/show.html.twig ou 404 si code inconnu
     */
    #[Route('/{code}', name: 'show', methods: ['GET'])]
    public function show(
        string $code,
        EquipementRepository $equipRepo,
        MaintenanceRepository $mainRepo
    ): Response {
        $equipement = $equipRepo->findOneBy(['codePasseport' => $code]);

        if (!$equipement) {
            throw $this->createNotFoundException('Passeport introuvable.');
        }

        // ── 3 dernières maintenances ─────────────────────────────────────────
        $maintenances = $mainRepo->findBy(
            ['equipementId' => $equipement->getId()],
            ['datePlanifiee' => 'DESC'],
            3
        );

        $sante  = $this->calculerSante($equipement, $maintenances);
        $qrUrl  = $this->generateUrl(
            'passeport_qr',
            ['id' => $equipement->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->render('equipment/passeport/show.html.twig', [
            'equipement'   => $equipement,
            'maintenances' => $maintenances,
            'sante'        => $sante,
            'qrUrl'        => $qrUrl,
        ]);
    }

    /**
     * Calcule l'indicateur de santé d'un équipement.
     *
     * Algorithme de priorité (du plus grave au moins grave) :
     *  1. Statut "Hors service" ou "En panne" → Critique (score 20, rouge)
     *  2. Au moins une maintenance en retard parmi les 3 dernières → Attention (score 55, jaune)
     *  3. Statut "En maintenance" → Attention (score 60, jaune)
     *  4. Tous les autres cas → Excellent (score 95, vert)
     *
     * Retourne un tableau avec : niveau, label, emoji, color (hex), score (0-100), message.
     * Utilisé dans le template pour l'indicateur visuel et la barre de progression.
     *
     * @param Equipement   $eq           Équipement dont on calcule la santé
     * @param Maintenance[] $maintenances Les 3 dernières maintenances (peut être vide)
     *
     * @return array{niveau: string, label: string, emoji: string, color: string, score: int, message: string}
     */
    private function calculerSante(Equipement $eq, array $maintenances): array
    {
        $statut = $eq->getStatut();

        // ── Niveau 1 : équipement hors d'usage ───────────────────────────────
        if (in_array($statut, ['Hors service', 'En panne'])) {
            return [
                'niveau'  => 'critique',
                'label'   => 'Critique',
                'emoji'   => '🔴',
                'color'   => '#dc3545',
                'score'   => 20,
                'message' => 'Équipement nécessite une intervention urgente.',
            ];
        }

        // ── Niveau 2 : maintenance en retard (date dépassée, statut actif) ───
        foreach ($maintenances as $m) {
            if ($m->isEnRetard()) {
                return [
                    'niveau'  => 'attention',
                    'label'   => 'Attention',
                    'emoji'   => '🟡',
                    'color'   => '#ffc107',
                    'score'   => 55,
                    'message' => 'Une maintenance est en retard.',
                ];
            }
        }

        // ── Niveau 3 : en cours de maintenance (pas forcément en retard) ─────
        if ($statut === 'En maintenance') {
            return [
                'niveau'  => 'attention',
                'label'   => 'En maintenance',
                'emoji'   => '🟡',
                'color'   => '#ffc107',
                'score'   => 60,
                'message' => 'Équipement en cours de maintenance.',
            ];
        }

        // ── Niveau 4 : tout va bien ──────────────────────────────────────────
        return [
            'niveau'  => 'excellent',
            'label'   => 'Excellent',
            'emoji'   => '🟢',
            'color'   => '#198754',
            'score'   => 95,
            'message' => 'Équipement en bon état de fonctionnement.',
        ];
    }
}
