<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\RentalRequest;
use App\Entity\Marketplace\Produits;
use App\Entity\UserManagement\User;
use App\Form\Marketplace\EquipmentRentalRequestType;
use App\Marketplace\TunisiaRegionList;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\Marketplace\PanierRepository;
use App\Repository\Marketplace\ProduitsRepository;
use App\Repository\UserManagement\UserRepository;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/marketplace')]
class MarketplacePublicController extends AbstractController
{
    public function __construct(
        private readonly ProduitsRepository $produitsRepository,
        private readonly UserRepository $userRepository,
        private readonly PanierRepository $panierRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'marketplace_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        [$cat, $region, $q, $sort, $sellerIdsForRegion] = $this->readFilters($request);
        $produits = $sellerIdsForRegion === []
            ? []
            : $this->produitsRepository->findPublicMarketplaceCatalog($q !== '' ? $q : null, $cat, $sellerIdsForRegion, $sort);

        $rentalRequest = new RentalRequest();
        $this->prefillRentalRequestFromUser($rentalRequest);
        $rentalForm = $this->createForm(EquipmentRentalRequestType::class, $rentalRequest);

        return $this->renderResponse($request, $cat, $region, $sort, $produits, $rentalForm, false, null);
    }

    #[Route('/rental/request', name: 'marketplace_rental_request_submit', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function submitRentalRequest(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        [$cat, $region, $q, $sort, $sellerIdsForRegion] = $this->readFilters($request);
        $produits = $sellerIdsForRegion === []
            ? []
            : $this->produitsRepository->findPublicMarketplaceCatalog($q !== '' ? $q : null, $cat, $sellerIdsForRegion, $sort);

        $rentalRequest = new RentalRequest();
        $this->prefillRentalRequestFromUser($rentalRequest);
        $rentalForm = $this->createForm(EquipmentRentalRequestType::class, $rentalRequest);
        $fallbackProductId = trim((string) $request->request->get('rental_target_product_id_fallback', ''));
        $formName = $rentalForm->getName();
        $submittedData = $request->request->all($formName);
        if (
            \is_array($submittedData)
            && ((string) ($submittedData['produit_id'] ?? '') === '')
            && $fallbackProductId !== ''
            && ctype_digit($fallbackProductId)
            && (int) $fallbackProductId > 0
        ) {
            $submittedData['produit_id'] = $fallbackProductId;
            $request->request->set($formName, $submittedData);
        }
        $rentalForm->handleRequest($request);

        $targetProductId = (int) $rentalForm->get('produit_id')->getData();
        if ($targetProductId <= 0) {
            $targetProductId = (int) $request->request->get('rental_target_product_id_fallback', 0);
        }
        $produit = $targetProductId > 0 ? $this->produitsRepository->find($targetProductId) : null;

        // ── Extra format guards (date year sanity — defend against browsers sending 2-digit years) ──
        if ($rentalForm->isSubmitted()) {
            $formName = $rentalForm->getName();
            $raw      = $request->request->all($formName);

            $rawStart = \is_array($raw) ? (string) ($raw['rentalStartAt'] ?? '') : '';
            $rawEnd   = \is_array($raw) ? (string) ($raw['rentalEndAt']   ?? '') : '';
            $rawBirth = \is_array($raw) ? (string) ($raw['dateNaissance'] ?? '') : '';

            if ($rawBirth !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawBirth)) {
                $rentalForm->get('dateNaissance')->addError(
                    new FormError('L\'année doit être sur 4 chiffres (format YYYY-MM-DD).')
                );
            }
            if ($rawStart !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $rawStart)) {
                $rentalForm->get('rentalStartAt')->addError(
                    new FormError('Le format de date/heure de début est invalide (année sur 4 chiffres).')
                );
            }
            if ($rawEnd !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $rawEnd)) {
                $rentalForm->get('rentalEndAt')->addError(
                    new FormError('Le format de date/heure de fin est invalide (année sur 4 chiffres).')
                );
            }
        }

        // ── Cross-field date range check (runs only when Symfony constraints already passed) ──
        if ($rentalForm->isSubmitted() && $rentalForm->isValid()) {
            $now   = new \DateTimeImmutable();
            $start = \DateTimeImmutable::createFromInterface($rentalRequest->getRentalStartAt());
            $end   = \DateTimeImmutable::createFromInterface($rentalRequest->getRentalEndAt());

            if ($start < $now) {
                $rentalForm->get('rentalStartAt')->addError(
                    new FormError('La date de début ne peut pas être dans le passé.')
                );
            }
            $minEndByDay = $start->setTime(0, 0)->modify('+1 day');
            if ($end < $minEndByDay) {
                $rentalForm->get('rentalEndAt')->addError(
                    new FormError('La date de fin doit être à partir du jour suivant la date de début.')
                );
            }
        }

        // ── Business-rule checks on the target product ──
        if ($rentalForm->isSubmitted() && $rentalForm->isValid()) {
            if (!$produit || !$produit->isRental() || !$produit->getActive()) {
                $rentalForm->get('produit_id')->addError(
                    new FormError('Cet équipement n\'est pas disponible en location.')
                );
            } elseif ($produit->getIdFournisseur() === (int) $user->getId()) {
                $rentalForm->get('produit_id')->addError(
                    new FormError('Vous ne pouvez pas louer votre propre équipement.')
                );
            } else {
                // All checks passed — persist the request.
                $start         = $rentalRequest->getRentalStartAt();
                $end           = $rentalRequest->getRentalEndAt();
                $durationHours = max(0.0, ($end->getTimestamp() - $start->getTimestamp()) / 3600);
                $rentalRequest->setRentalDurationHours(round($durationHours, 2));
                $rentalRequest->setProduitId($produit->getId());
                $rentalRequest->setVendeurId((int) $produit->getIdFournisseur());
                $rentalRequest->setLocataireId((int) $user->getId());

                $this->entityManager->persist($rentalRequest);
                $this->entityManager->flush();
                $this->addFlash('success', 'Votre demande de location a été envoyée au vendeur.');

                return $this->redirectToRoute('marketplace_index', $request->query->all());
            }
        }

        return $this->renderResponse(
            $request, $cat, $region, $sort, $produits,
            $rentalForm, true,
            $targetProductId > 0 ? $targetProductId : null
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param Produits[] $produits
     */
    private function renderResponse(
        Request $request,
        string $cat,
        string $region,
        string $sort,
        array $produits,
        ?FormInterface $rentalForm = null,
        bool $openRentalRequestModal = false,
        ?int $rentalTargetProductId = null,
    ): Response {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (Produits $p) => $p->getIdFournisseur(),
            $produits
        ))));

        $vendeurs = [];
        if ($ids !== []) {
            foreach ($this->userRepository->findBy(['id' => $ids]) as $u) {
                $vendeurs[(int) $u->getId()] = [
                    'name' => $u->getDisplayName(),
                    'ville' => $u->getVille(),
                ];
            }
        }

        $panierCount = 0;
        $user = $this->getUser();
        if ($user instanceof User && $user->getId() !== null) {
            $panierCount = $this->panierRepository->countLignesProduitsPourUtilisateur((int) $user->getId());
        }

        $queryParams = $request->query->all();
        if (isset($queryParams['q']) && trim((string) $queryParams['q']) === '') {
            unset($queryParams['q']);
        }

        $session      = $request->getSession();
        $orderConfirm = $session->get('marketplace_order_confirm');
        if ($orderConfirm !== null) {
            $session->remove('marketplace_order_confirm');
        }
        if (!\is_array($orderConfirm) || empty($orderConfirm['numCommande'])) {
            $orderConfirm = null;
        }

        if ($rentalForm === null) {
            $rr = new RentalRequest();
            $this->prefillRentalRequestFromUser($rr);
            $rentalForm = $this->createForm(EquipmentRentalRequestType::class, $rr);
        }

        $rentalTargetProductName = null;
        $rentalTargetProductPrice = null;
        if ($rentalTargetProductId !== null && $rentalTargetProductId > 0) {
            $targetProduit = null;
            foreach ($produits as $p) {
                if ($p->getId() === $rentalTargetProductId) {
                    $targetProduit = $p;
                    break;
                }
            }
            if ($targetProduit === null) {
                $targetProduit = $this->produitsRepository->find($rentalTargetProductId);
            }
            if ($targetProduit instanceof Produits) {
                $rentalTargetProductName = $targetProduit->getNom();
                $rentalTargetProductPrice = $targetProduit->getRentalPricePerDay();
            }
        }

        return $this->render('marketplace/public/index.html.twig', [
            'produits'                  => $produits,
            'vendeurs'                  => $vendeurs,
            'tunisia_regions'           => TunisiaRegionList::getGovernorates(),
            'filter_cat'                => $cat,
            'filter_region'             => $region,
            'filter_sort'               => $sort,
            'filter_q'                  => trim($request->query->getString('q', '')),
            'query_params'              => $queryParams,
            'panier_count'              => $panierCount,
            'order_confirm'             => $orderConfirm,
            'rental_request_form'       => $rentalForm->createView(),
            'open_rental_request_modal' => $openRentalRequestModal,
            'rental_target_product_id'  => $rentalTargetProductId,
            'rental_target_product_name' => $rentalTargetProductName,
            'rental_target_product_price' => $rentalTargetProductPrice,
        ]);
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: ?array}
     */
    private function readFilters(Request $request): array
    {
        $cat    = strtolower($request->query->getString('cat', 'all'));
        $region = trim($request->query->getString('region', ''));
        $q      = trim($request->query->getString('q', ''));
        $sort   = $request->query->getString('sort', 'price_asc');

        if (!\in_array($sort, ['price_asc', 'price_desc'], true)) {
            $sort = 'price_asc';
        }
        $allowedCat = ['all', 'legume', 'fruit', 'graines', 'equipement'];
        if (!\in_array($cat, $allowedCat, true)) {
            $cat = 'all';
        }

        $sellerIdsForRegion = null;
        if ($region !== '') {
            $sellerIdsForRegion = $this->userRepository->findUserIdsByVille($region);
        }

        return [$cat, $region, $q, $sort, $sellerIdsForRegion];
    }

    private function prefillRentalRequestFromUser(RentalRequest $rentalRequest): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return;
        }

        $displayName  = $user->getFournisseurRaisonSocial() ?: $user->getDisplayName();
        $addressParts = array_filter([$user->getGouvernant(), $user->getVille(), $user->getCodePostale()]);

        $rentalRequest
            ->setFullName($displayName)
            ->setEmail((string) ($user->getEmail() ?? ''))
            ->setPhone((string) ($user->getTelephone() ?? ''))
            ->setDateNaissance($user->getDateNaissance())
            ->setAddress($addressParts !== [] ? implode(', ', $addressParts) : '');
    }
}