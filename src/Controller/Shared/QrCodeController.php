<?php

namespace App\Controller\Shared;

use App\Entity\Activity\Evenement;
use App\Entity\Activity\Activite;
use App\Service\QrCodeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/qr')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class QrCodeController extends AbstractController
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
    ) {
    }

    /**
     * Generate QR code for an event pointing to agrilink-gr/event.html
     */
    #[Route('/event/{id}', name: 'qr_event', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function generateEventQr(Evenement $evenement): JsonResponse
    {
        try {
            // Extract date and time from dateEvenement
            $dateEvenement = $evenement->getDateEvenement();
            $date = $dateEvenement?->format('d/m/Y') ?? '';
            $heure = $dateEvenement?->format('H:i') ?? '';

            // Build parameters array for QR code URL
            $params = [
                'titre' => $evenement->getTitre() ?? '',
                'date' => $date,
                'heure' => $heure,
                'lieu' => $evenement->getLieu() ?? '',
                'type' => $evenement->getTypeEvenement() ?? '',
                'desc' => $evenement->getDescription() ?? '',
            ];

            $qrUrl = $this->qrCodeService->generateQrCodeForEvent($params);

            return $this->json([
                'success' => true,
                'type' => 'event',
                'title' => $evenement->getTitre(),
                'date' => "{$date} {$heure}",
                'qr_url' => $qrUrl,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la génération du code QR',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate QR code for an activity pointing to agrilink-gr/event.html
     */
    #[Route('/activity/{id}', name: 'qr_activity', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function generateActivityQr(Activite $activite): JsonResponse
    {
        try {
            // Extract dates from activity
            $dateDebut = $activite->getDateDebut();
            $dateFin = $activite->getDateFin();
            $debut = $dateDebut?->format('d/m/Y') ?? '';
            $fin = $dateFin?->format('d/m/Y') ?? '';

            // Build parameters array for QR code URL
            $params = [
                'titre' => $activite->getTitre() ?? '',
                'type_act' => $activite->getTypeActivite() ?? '',
                'debut' => $debut,
                'fin' => $fin,
                'statut' => $activite->getStatut() ?? '',
                'cout' => $activite->getCoutEstime() ?? '',
            ];

            $qrUrl = $this->qrCodeService->generateQrCodeForActivity($params);

            return $this->json([
                'success' => true,
                'type' => 'activity',
                'title' => $activite->getTitre(),
                'date' => $debut,
                'qr_url' => $qrUrl,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la génération du code QR',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
