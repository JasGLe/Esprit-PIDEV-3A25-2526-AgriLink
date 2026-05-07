<?php

namespace App\Controller\Marketplace;

use App\Entity\UserManagement\User;
use App\Service\Marketplace\Sales\SalesStatsPayloadBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/statistiques-ventes', name: 'sales_stats_')]
final class SalesDashboardController extends AbstractController
{
    public function __construct(
        private readonly SalesStatsPayloadBuilder $payloadBuilder,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $sellerId = $this->getSellerIdOrDeny();
        $payload = $this->buildPayload($sellerId);

        return $this->render('marketplace/mes_locations/stats.html.twig', [
            'payload' => $payload,
            'stats_data_url' => $this->generateUrl('sales_stats_data'),
        ]);
    }

    #[Route('/data', name: 'data', methods: ['GET'])]
    public function data(): JsonResponse
    {
        $sellerId = $this->getSellerIdOrDeny();

        return $this->json($this->buildPayload($sellerId));
    }

    private function getSellerIdOrDeny(): int
    {
        if (!$this->isGranted('ROLE_AGRICULTEUR')) {
            throw $this->createAccessDeniedException();
        }

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return (int) $user->getId();
    }

    /**
     * @return array{
     *   kpis: array{revenue_today: float, revenue_month: float, revenue_year: float, orders_total: int},
     *   top_products: list<array{name: string, quantity: int, revenue: float}>,
     *   evolution: array{labels: list<string>, data: list<float>},
     *   categories: array{labels: list<string>, data: list<float>}
     * }
     */
    private function buildPayload(int $sellerUserId): array
    {
        return $this->payloadBuilder->build($sellerUserId);
    }

}

