<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Produits;
use App\Entity\Marketplace\RentalRequest;
use App\Entity\UserManagement\User;
use App\Repository\Marketplace\ProduitsRepository;
use App\Repository\Marketplace\RentalRequestRepository;
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mes-locations', name: 'mes_locations_')]
#[IsGranted('ROLE_USER')]
class MesLocationsController extends AbstractController
{
    private const SELLER_TRANSPORT_FEE_DT = 10.0;

    public function __construct(
        private readonly RentalRequestRepository $rentalRequestRepository,
        private readonly ProduitsRepository $produitsRepository,
        private readonly Pdf $snappyPdf,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyUnlessSellerOrAdmin($user);

        if ($this->isGranted('ROLE_ADMIN')) {
            $requests = $this->rentalRequestRepository->findBy([], ['id' => 'DESC']);
        } else {
            $requests = $this->rentalRequestRepository->findForSeller((int) $user->getId());
        }

        [$productsById, $estimatedAmounts] = $this->buildProductsAndAmountMaps($requests);
        $total = \count($requests);

        return $this->render('marketplace/mes_locations/index.html.twig', [
            'rental_requests' => $requests,
            'products_by_id' => $productsById,
            'estimated_amounts' => $estimatedAmounts,
            'count_total' => $total,
            'count_en_attente' => $total,
            'count_traitees' => 0,
        ]);
    }

    #[Route('/{id}/detail-modal', name: 'detail_modal', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detailModal(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $requestEntity = $this->findAccessibleRequest($id, $user);
        $product = $this->produitsRepository->find($requestEntity->getProduitId());

        return $this->render('marketplace/mes_locations/_detail_modal_fragment.html.twig', [
            'request_item' => $requestEntity,
            'product' => $product,
            'estimated_amount' => $this->estimateAmount($requestEntity, $product instanceof Produits ? $product : null),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('delete_rental_request_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $requestEntity = $this->findAccessibleRequest($id, $user);
        $this->rentalRequestRepository->remove($requestEntity, true);
        $this->addFlash('success', 'La demande de location a été supprimée.');

        return $this->redirectToRoute('mes_locations_index');
    }

    #[Route('/{id}/pdf', name: 'pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function pdf(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $requestEntity = $this->findAccessibleRequest($id, $user);
        $product = $this->produitsRepository->find($requestEntity->getProduitId());

        $estimated = $this->estimateAmount($requestEntity, $product instanceof Produits ? $product : null);
        $html = $this->renderView('marketplace/mes_locations/pdf_invoice.html.twig', [
            'request_item' => $requestEntity,
            'product' => $product,
            'estimated_amount' => $estimated,
        ]);
        $filename = 'facture_location_'.$requestEntity->getId().'.pdf';
        $output = $this->snappyPdf->getOutputFromHtml($html, [
            'encoding' => 'utf-8',
            'margin-top' => 12,
            'margin-right' => 10,
            'margin-bottom' => 12,
            'margin-left' => 10,
            'enable-local-file-access' => true,
        ]);

        return new Response($output, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param list<RentalRequest> $requests
     *
     * @return array{0: array<int, Produits>, 1: array<int, float>}
     */
    private function buildProductsAndAmountMaps(array $requests): array
    {
        $productIds = [];
        foreach ($requests as $r) {
            $productIds[] = $r->getProduitId();
        }
        $productIds = array_values(array_unique(array_filter($productIds)));

        $productsById = [];
        if ($productIds !== []) {
            foreach ($this->produitsRepository->findBy(['id' => $productIds]) as $p) {
                $productsById[$p->getId()] = $p;
            }
        }

        $estimatedAmounts = [];
        foreach ($requests as $r) {
            $product = $productsById[$r->getProduitId()] ?? null;
            $estimatedAmounts[$r->getId()] = $this->estimateAmount($r, $product);
        }

        return [$productsById, $estimatedAmounts];
    }

    private function estimateAmount(RentalRequest $requestEntity, ?Produits $product): float
    {
        $savedTotal = (float) $requestEntity->getTotalPrice();
        if ($savedTotal > 0) {
            return round($savedTotal, 3);
        }

        if (!$product instanceof Produits || $product->getRentalPricePerDay() === null) {
            return 0.0;
        }

        $hours = max(0.0, (float) $requestEntity->getRentalDurationHours());
        $days = $hours / 24;

        $baseAmount = $days * (float) $product->getRentalPricePerDay();
        $transportValue = strtolower(trim((string) $requestEntity->getTransportResponsibility()));
        $transportFee = \in_array($transportValue, ['seller', 'vendeur'], true) ? self::SELLER_TRANSPORT_FEE_DT : 0.0;

        return round($baseAmount + $transportFee, 3);
    }

    private function denyUnlessSellerOrAdmin(User $user): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }
        if ($this->isGranted('ROLE_AGRICULTEUR') || $this->isGranted('ROLE_FOURNISSEUR')) {
            return;
        }

        throw $this->createAccessDeniedException();
    }

    private function findAccessibleRequest(int $id, User $user): RentalRequest
    {
        $requestEntity = $this->rentalRequestRepository->find($id);
        if (!$requestEntity instanceof RentalRequest) {
            throw $this->createNotFoundException();
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return $requestEntity;
        }
        $this->denyUnlessSellerOrAdmin($user);

        if ($requestEntity->getVendeurId() !== (int) $user->getId()) {
            throw $this->createNotFoundException();
        }

        return $requestEntity;
    }
}
