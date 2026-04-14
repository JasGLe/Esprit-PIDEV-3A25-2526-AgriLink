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


#[Route('/passeport', name: 'passeport_')]
class PasseportController extends AbstractController
{
    #[Route('/qr/{id}', name: 'qr', methods: ['GET'], requirements: ['id' => '\d+'])]
public function qr(
    Equipement $equipement,
    EntityManagerInterface $em,
    Request $request                // ← AJOUT
): Response {
    if (!$equipement->getCodePasseport()) {
        $equipement->setCodePasseport($equipement->genererCodePasseport());
        $em->flush();
    }

    // ── URL publique via APP_PUBLIC_URL ou host actuel ──
    $baseUrl = $_ENV['APP_PUBLIC_URL'] ?? $request->getSchemeAndHttpHost();

    $url = $baseUrl . $this->generateUrl(
        'passeport_show_by_id',
        ['id' => $equipement->getId()]
    );

    // Génération QR
    $renderer = new ImageRenderer(
        new RendererStyle(300),
        new SvgImageBackEnd()
    );
    $writer = new Writer($renderer);
    $svg    = $writer->writeString($url);

    return new Response($svg, 200, ['Content-Type' => 'image/svg+xml']);
}

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

        if (!$equipement->getCodePasseport()) {
            $equipement->setCodePasseport($equipement->genererCodePasseport());
            $em->flush();
        }

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

    private function calculerSante(Equipement $eq, array $maintenances): array
    {
        $statut = $eq->getStatut();

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