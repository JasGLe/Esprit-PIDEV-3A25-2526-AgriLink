<?php

namespace App\Controller\UserManagement\Admin;

use App\Entity\Marketplace\Produits;
use App\Entity\Notifications;
use App\Entity\UserManagement\User;
use App\Repository\Marketplace\ProduitsRepository;
use App\Repository\NotificationsRepository;
use App\Repository\UserManagement\UserRepository;
use App\Service\BanService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/moderation/boutique', name: 'admin_moderation_boutique_')]
#[IsGranted('ROLE_ADMIN')]
final class ModerationController extends AbstractController
{
    private const MODERATION_NOTIFICATION_TYPE = 'marketplace_product_submitted';
    private const SELLER_UNBAN_NOTIFICATION_TYPE = 'marketplace_seller_unbanned';

    public function __construct(
        private readonly ProduitsRepository $produitsRepository,
        private readonly UserRepository $userRepository,
        private readonly NotificationsRepository $notificationsRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly BanService $banService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $admin */
        $admin = $this->getUser();
        $adminId = (int) $admin->getId();
        $this->notificationsRepository->markAllReadByUserIdAndType($adminId, self::MODERATION_NOTIFICATION_TYPE);

        $products = $this->produitsRepository->findRecentForModeration(50);
        $productNotifications = $this->notificationsRepository->findRecentByUserAndType($admin, self::MODERATION_NOTIFICATION_TYPE, 200);
        $createdAtByProductId = [];
        foreach ($productNotifications as $notification) {
            $pid = $notification->getProductId();
            if ($pid !== null && !isset($createdAtByProductId[$pid])) {
                $createdAtByProductId[$pid] = $notification->getCreatedAt();
            }
        }

        $sellerIds = array_values(array_unique(array_filter(array_map(
            static fn (Produits $p): ?int => $p->getIdFournisseur(),
            $products
        ))));

        $sellersById = [];
        if ($sellerIds !== []) {
            foreach ($this->userRepository->findBy(['id' => $sellerIds]) as $seller) {
                $sellersById[(int) $seller->getId()] = $seller;
            }
        }

        return $this->render('admin/moderation/index.html.twig', [
            'products' => $products,
            'sellersById' => $sellersById,
            'notifications' => $this->notificationsRepository->findRecentByUserAndType($admin, self::MODERATION_NOTIFICATION_TYPE, 10),
            'createdAtByProductId' => $createdAtByProductId,
        ]);
    }

    #[Route('/approve/{id}', name: 'approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approve(Produits $produit, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('moderation_approve_'.$produit->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_moderation_boutique_index');
        }

        $produit->setModerationStatus(Produits::MODERATION_APPROVED);
        $this->entityManager->flush();
        $this->addFlash('success', 'Produit approuvé.');

        return $this->redirectToRoute('admin_moderation_boutique_index');
    }

    #[Route('/ban-seller/{id}', name: 'ban_seller', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function banSeller(Produits $produit, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('moderation_ban_'.$produit->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_moderation_boutique_index');
        }

        $sellerId = (int) ($produit->getIdFournisseur() ?? 0);
        $seller = $sellerId > 0 ? $this->userRepository->find($sellerId) : null;
        if (!$seller instanceof User) {
            $this->addFlash('error', 'Vendeur introuvable.');
            return $this->redirectToRoute('admin_moderation_boutique_index');
        }

        $reason = trim((string) $request->request->get('reason', 'Produit non conforme aux règles de la boutique.'));
        if ($reason === '') {
            $reason = 'Produit non conforme aux règles de la boutique.';
        }

        $this->banService->banUser($seller, $reason);
        $produit->setModerationStatus(Produits::MODERATION_BANNED);
        $this->entityManager->flush();

        $this->addFlash('success', 'Vendeur banni et produit marqué comme banni.');

        return $this->redirectToRoute('admin_moderation_boutique_index');
    }

    #[Route('/unban/{id}', name: 'unban_seller', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function unbanSeller(User $seller, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('moderation_unban_'.$seller->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_moderation_boutique_index');
        }

        $this->banService->liftBan($seller);

        // Keep moderation view consistent: seller unbanned => previously banned products
        // go back to pending review.
        $sellerProducts = $this->produitsRepository->findBy([
            'idFournisseur' => (int) $seller->getId(),
            'moderationStatus' => Produits::MODERATION_BANNED,
        ]);
        foreach ($sellerProducts as $product) {
            $product->setModerationStatus(Produits::MODERATION_PENDING);
        }

        $sellerNotification = (new Notifications())
            ->setUser($seller)
            ->setType(self::SELLER_UNBAN_NOTIFICATION_TYPE)
            ->setTitle('Acces boutique reactive')
            ->setBody('Votre suspension boutique a ete levee. Vous pouvez publier et gerer vos produits a nouveau.')
            ->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($sellerNotification);

        $this->entityManager->flush();

        $this->addFlash('success', 'Vendeur débanni. Les produits bannis sont remis en attente.');
        return $this->redirectToRoute('admin_moderation_boutique_index');
    }

    #[Route('/notifications/unread-count', name: 'unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();
        return $this->json([
            'count' => $this->notificationsRepository->countUnreadByUserAndType($admin, self::MODERATION_NOTIFICATION_TYPE),
        ]);
    }
}

