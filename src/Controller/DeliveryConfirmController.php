<?php

namespace App\Controller;

use App\Entity\Marketplace\Commandes;
use App\Repository\Marketplace\CommandesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/delivery')]
final class DeliveryConfirmController extends AbstractController
{
    public function __construct(
        private readonly CommandesRepository $commandesRepository,
    ) {
    }

    #[Route('/confirm/{token}', name: 'delivery_confirm', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET', 'POST'])]
    public function confirm(Request $request, string $token): Response
    {
        $commande = $this->commandesRepository->findOneBy(['deliveryConfirmationToken' => $token]);
        if (!$commande instanceof Commandes) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            return $this->handleConfirmPost($request, $commande);
        }

        return $this->renderConfirmGet($commande);
    }

    private function handleConfirmPost(Request $request, Commandes $commande): Response
    {
        if (!$this->isCsrfTokenValid('delivery_confirm_'.$commande->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($commande->getStatus() === 'LIVREE') {
            $this->addFlash('info', 'Cette livraison a déjà été confirmée.');

            return $this->redirectToRoute('delivery_confirm', ['token' => (string) $commande->getDeliveryConfirmationToken()]);
        }

        if ($commande->getStatus() !== 'EXPEDIEE') {
            $this->addFlash('error', 'Cette commande ne peut pas être confirmée (statut actuel : '.$commande->getStatus().').');

            return $this->redirectToRoute('delivery_confirm', ['token' => (string) $commande->getDeliveryConfirmationToken()]);
        }

        $commande->setStatus('LIVREE');
        $this->commandesRepository->save($commande, true);
        $this->addFlash('success', 'Livraison confirmée. La commande est marquée comme livrée.');

        return $this->redirectToRoute('delivery_confirm', ['token' => (string) $commande->getDeliveryConfirmationToken()]);
    }

    private function renderConfirmGet(Commandes $commande): Response
    {
        $state = match (true) {
            $commande->getStatus() === 'LIVREE' => 'delivered',
            $commande->getStatus() === 'EXPEDIEE' => 'pending',
            default => 'invalid',
        };

        return $this->render('delivery/confirm.html.twig', [
            'commande' => $commande,
            'state' => $state,
        ]);
    }
}
