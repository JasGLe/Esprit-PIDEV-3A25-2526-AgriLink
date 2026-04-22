<?php

namespace App\Controller\Marketplace;

use App\Dto\Marketplace\BoutiqueEquipementVenteDto;
use App\Entity\Equipement;
use App\Entity\Exploitation\Culture;
use App\Entity\Marketplace\Produits;
use App\Entity\UserManagement\User;
use App\Form\Marketplace\BoutiqueCultureProduitType;
use App\Form\Marketplace\BoutiqueEquipementVenteType;
use App\Form\Marketplace\EquipementRentalType;
use App\Form\Marketplace\PromoCodeAssignType;
use App\Repository\Marketplace\ProduitsRepository;
use App\Repository\NotificationsRepository;
use App\Service\BanService;
use App\Service\PriceOptimizerService;
use App\Service\MailtrapContactService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/boutique')]
#[IsGranted('ROLE_USER')]
#[IsGranted(new Expression('is_granted("ROLE_FOURNISSEUR") or (is_granted("ROLE_AGRICULTEUR") and not is_granted("ROLE_AGRIPLUS"))'))]
class BoutiqueController extends AbstractController
{
    private const UNBAN_NOTIFICATION_TYPE = 'marketplace_seller_unbanned';

    private const CATEGORY_LABELS = [
        'LEGUME' => 'Légume',
        'FRUIT' => 'Fruit',
        'GRAINS' => 'Grains',
    ];

    public function __construct(
        private readonly ProduitsRepository $produitsRepository,
        private readonly NotificationsRepository $notificationsRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly BanService $banService,
        private readonly PriceOptimizerService $priceOptimizerService,
        private readonly MailtrapContactService $mailtrapContactService,
        private readonly string $adminContactEmail,
    ) {
    }

    #[Route('/ai/price-optimizer/suggest', name: 'boutique_price_optimizer_suggest', methods: ['POST'])]
    public function suggestPrice(Request $request): JsonResponse
    {
        if (($blocked = $this->redirectIfBanned()) instanceof Response) {
            return $this->json(['ok' => false, 'message' => 'Acces bloque.'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            $payload = [];
        }

        $nom = trim((string) ($payload['nom'] ?? ''));
        $category = trim((string) ($payload['category'] ?? ''));
        $uniteVente = trim((string) ($payload['uniteVente'] ?? ''));
        $quantite = (int) ($payload['quantite'] ?? 0);

        $result = $this->priceOptimizerService->suggest($nom, $category, $uniteVente, $quantite);

        return $this->json(array_merge(['ok' => true], $result));
    }

    #[Route('', name: 'boutique_index', methods: ['GET'])]
    public function index(): Response
    {
        if (($blocked = $this->redirectIfBanned()) instanceof Response) {
            return $blocked;
        }
        /** @var User $user */
        $user = $this->getUser();
        $uid = (int) $user->getId();
        $unbanNotifs = $this->notificationsRepository->findUnreadByUserIdAndType($uid, self::UNBAN_NOTIFICATION_TYPE, 1);
        if ($unbanNotifs !== []) {
            $this->addFlash('success', 'Votre acces a la boutique a ete reactive par l administrateur.');
            $this->notificationsRepository->markAllReadByUserIdAndType($uid, self::UNBAN_NOTIFICATION_TYPE);
        }
        $promoForm = $this->createForm(PromoCodeAssignType::class);
        $rentalForm = $this->createForm(EquipementRentalType::class);

        return $this->renderBoutiqueIndex($uid, $promoForm, false, $rentalForm, false, null);
    }

    #[Route('/promo-code/assign', name: 'boutique_promo_assign', methods: ['POST'])]
    public function assignPromoCode(Request $request): Response
    {
        if (($blocked = $this->redirectIfBanned()) instanceof Response) {
            return $blocked;
        }
        /** @var User $user */
        $user = $this->getUser();
        $uid = (int) $user->getId();
        $produits = $this->produitsRepository->findBoutiqueByProprietaire($uid);

        $form = $this->createForm(PromoCodeAssignType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->renderBoutiqueIndex($uid, $form, true);
        }

        $selectedIdsRaw = $form->get('product_ids')->getData();
        $selectedIds = [];
        if (\is_array($selectedIdsRaw)) {
            $selectedIds = array_map('intval', $selectedIdsRaw);
        } elseif (\is_string($selectedIdsRaw) && trim($selectedIdsRaw) !== '') {
            $selectedIds = array_map('intval', preg_split('/\s*,\s*/', trim($selectedIdsRaw)) ?: []);
        }
        $selectedIds = array_values(array_unique(array_filter($selectedIds, static fn (int $v) => $v > 0)));

        if ($selectedIds === []) {
            $form->get('product_ids')->addError(new FormError('Sélectionnez au moins un produit.'));
        }

        $ownedIds = array_map(static fn (Produits $p) => $p->getId(), $produits);
        foreach ($selectedIds as $id) {
            if (!\in_array($id, $ownedIds, true)) {
                $form->get('product_ids')->addError(new FormError('Un produit sélectionné ne vous appartient pas.'));
                break;
            }
        }

        if (!$form->isValid()) {
            return $this->renderBoutiqueIndex($uid, $form, true);
        }

        $code = mb_strtoupper(trim((string) $form->get('code')->getData()));
        $discountPercent = (float) $form->get('discount_percent')->getData();
        /** @var \DateTimeInterface|null $startDate */
        $startDate = $form->get('start_date')->getData();
        /** @var \DateTimeInterface|null $endDate */
        $endDate = $form->get('end_date')->getData();

        if ($startDate === null || $endDate === null) {
            return $this->renderBoutiqueIndex($uid, $form, true);
        }

        foreach ($produits as $produit) {
            if (!\in_array($produit->getId(), $selectedIds, true)) {
                continue;
            }
            $produit->setPromoCode($code);
            $produit->setPromoDiscountPercent($discountPercent);
            $produit->setPromoActive(true);
            $produit->setPromoStartAt(clone $startDate);
            $produit->setPromoEndAt(clone $endDate);
        }

        $this->entityManager->flush();
        $this->addFlash('success', 'Code promo enregistré sur les produits sélectionnés.');

        return $this->redirectToRoute('boutique_index');
    }

    #[Route('/equipement/location/activer', name: 'boutique_equipement_location_activer', methods: ['POST'])]
    public function activateEquipmentRental(Request $request): Response
    {
        if (($blocked = $this->redirectIfBanned()) instanceof Response) {
            return $blocked;
        }
        /** @var User $user */
        $user = $this->getUser();
        $uid = (int) $user->getId();
        $promoForm = $this->createForm(PromoCodeAssignType::class);
        $rentalForm = $this->createForm(EquipementRentalType::class);
        $rentalForm->handleRequest($request);

        if (!$rentalForm->isSubmitted() || !$rentalForm->isValid()) {
            return $this->renderBoutiqueIndex($uid, $promoForm, false, $rentalForm, true, null);
        }

        $productId = (int) $rentalForm->get('product_id')->getData();
        $produit = $this->produitsRepository->find($productId);
        if (!$produit || !$this->isBoutiqueProductOwnedByUser($produit) || $produit->getEquipementId() === null) {
            $rentalForm->get('product_id')->addError(new FormError('Équipement introuvable ou non autorisé.'));

            return $this->renderBoutiqueIndex($uid, $promoForm, false, $rentalForm, true, $productId > 0 ? $productId : null);
        }

        $rentalPrice = (float) $rentalForm->get('rental_price_per_day')->getData();
        $rentalDescriptionRaw = trim((string) $rentalForm->get('rental_description')->getData());
        $rentalDescription = $rentalDescriptionRaw !== '' ? $rentalDescriptionRaw : null;

        $produit->setIsRental(true);
        $produit->setRentalPricePerDay($rentalPrice);
        $produit->setRentalDescription($rentalDescription);
        if ($rentalDescription !== null) {
            $produit->setDescription($rentalDescription);
        }
        $this->entityManager->flush();

        $this->addFlash('success', 'L’équipement est maintenant disponible en location.');

        return $this->redirectToRoute('boutique_index');
    }

    private function renderBoutiqueIndex(
        int $uid,
        FormInterface $promoForm,
        bool $openPromoModal,
        ?FormInterface $rentalForm = null,
        bool $openRentalModal = false,
        ?int $rentalModalTargetId = null
    ): Response
    {
        $produits = $this->produitsRepository->findBoutiqueByProprietaire($uid);
        $rentalForm ??= $this->createForm(EquipementRentalType::class);

        return $this->render('marketplace/boutique/index.html.twig', [
            'produits' => $produits,
            'promo_form' => $promoForm->createView(),
            'rental_form' => $rentalForm->createView(),
            'active_promos' => $this->buildActivePromos($produits),
            'open_promo_modal' => $openPromoModal,
            'open_rental_modal' => $openRentalModal,
            'rental_modal_target_id' => $rentalModalTargetId,
        ]);
    }

    /**
     * @param Produits[] $produits
     * @return array<int, array{code: string, discountPercent: float, productsCount: int, startDate: ?string, endDate: ?string}>
     */
    private function buildActivePromos(array $produits): array
    {
        $activePromos = [];
        foreach ($produits as $produit) {
            if (!$produit->isPromoActive() || !$produit->getPromoCode() || $produit->getPromoDiscountPercent() === null) {
                continue;
            }
            $start = $produit->getPromoStartAt()?->format('Y-m-d');
            $end = $produit->getPromoEndAt()?->format('Y-m-d');
            $key = $produit->getPromoCode().'|'.(string) $produit->getPromoDiscountPercent().'|'.$start.'|'.$end;
            $activePromos[$key] = [
                'code' => $produit->getPromoCode(),
                'discountPercent' => $produit->getPromoDiscountPercent(),
                'productsCount' => ($activePromos[$key]['productsCount'] ?? 0) + 1,
                'startDate' => $start,
                'endDate' => $end,
            ];
        }

        return array_values($activePromos);
    }
    #[Route('/produit/culture/{cultureId}/nouveau', name: 'boutique_produit_depuis_culture', requirements: ['cultureId' => '\\d+'], methods: ['GET', 'POST'])]
    public function nouveauDepuisCulture(int $cultureId, Request $request): Response
    {
        if (($blocked = $this->redirectIfBanned()) instanceof Response) {
            return $blocked;
        }
        $culture = $this->entityManager->find(Culture::class, $cultureId);
        if (!$culture) {
            throw $this->createNotFoundException('Culture introuvable.');
        }

        /** @var User $user */
        $user = $this->getUser();
        $this->assertUserOwnsCulture($culture, $user);
        if ($this->produitsRepository->existsBoutiqueProduitForCulture((int) $user->getId(), (int) $culture->getId())) {
            $this->addFlash('info', 'Cette culture est déjà en boutique.');

            return $this->redirectToRoute('exploitation_index');
        }

        $produit = new Produits();
        $produit->setNom((string) $culture->getNom());
        $produit->setCategorie('Légume · kg');
        $produit->setPrixUnitaire(0.0);
        $produit->setImage($culture->getImage() ?: '');
        $produit->setOrigine(ProduitsRepository::ORIGINE_BOUTIQUE_AGRICULTEUR);
        $produit->setActive(true);
        $produit->setQuantite(1);
        $produit->setCategory('LEGUME');

        $form = $this->createForm(BoutiqueCultureProduitType::class, $produit, [
            'lock_nom' => true,
        ]);
        $form->handleRequest($request);

        $fromModal = (bool) $request->request->get('_from_modal');

        if ($request->isMethod('POST')) {
            if (!$form->isSubmitted()) {
                $this->addFlash('error', 'Formulaire incomplet ou non soumis correctement.');
                if ($fromModal) {
                    return $this->redirectToRoute('exploitation_index');
                }

                return $this->redirectToRoute('boutique_produit_depuis_culture', ['cultureId' => $cultureId]);
            }

            if (!$form->isValid()) {
                if ($fromModal) {
                    foreach ($form->getErrors(true) as $error) {
                        $this->addFlash('error', $error->getMessage());
                    }

                    return $this->redirectToRoute('exploitation_index');
                }

                return $this->render('marketplace/boutique/from_culture.html.twig', [
                    'form' => $form,
                    'culture' => $culture,
                ]);
            }

            if ($this->produitsRepository->existsBoutiqueProduitForCulture((int) $user->getId(), (int) $culture->getId())) {
                $this->addFlash('info', 'Cette culture est déjà en boutique.');

                return $this->redirectToRoute('exploitation_index');
            }
            $this->applyCategorieEtUnite($produit, (string) $form->get('uniteVente')->getData());
            $produit->setCultureId($culture->getId());
            $produit->setIdFournisseur((int) $user->getId());
            $produit->setOrigine(ProduitsRepository::ORIGINE_BOUTIQUE_AGRICULTEUR);
            $produit->setImage($culture->getImage() ?: '');

            $this->entityManager->persist($produit);
            $this->entityManager->flush();
            $this->addFlash('success', 'Produit publié dans votre boutique.');

            if ($fromModal) {
                return $this->redirectToRoute('exploitation_index');
            }

            return $this->redirectToRoute('boutique_index');
        }

        return $this->render('marketplace/boutique/from_culture.html.twig', [
            'form' => $form,
            'culture' => $culture,
        ]);
    }

    #[Route('/produit/equipement/{equipementId}/nouveau', name: 'boutique_produit_depuis_equipement', requirements: ['equipementId' => '\\d+'], methods: ['POST'])]
    public function nouveauDepuisEquipement(int $equipementId, Request $request): Response
    {
        if (($blocked = $this->redirectIfBanned()) instanceof Response) {
            return $blocked;
        }
        /** @var User $user */
        $user = $this->getUser();
        $uid = (int) $user->getId();

        $equipement = $this->entityManager->find(Equipement::class, $equipementId);
        if (!$equipement) {
            throw $this->createNotFoundException('Équipement introuvable.');
        }

        if ((int) $equipement->getUserlog() !== $uid && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas mettre en vente cet équipement.');
        }

        if (!$this->isCsrfTokenValid('boutique_equipement_vente_' . $equipementId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('equipement_index');
        }

        if ($this->produitsRepository->existsBoutiqueProduitForEquipement($uid, $equipementId)) {
            $this->addFlash('info', 'Cet équipement est déjà en boutique.');
            return $this->redirectToRoute('equipement_index');
        }

        $dto = new BoutiqueEquipementVenteDto();
        $form = $this->createForm(BoutiqueEquipementVenteType::class, $dto);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            $this->addFlash('error', 'Formulaire incomplet ou non soumis correctement.');
            return $this->redirectToRoute('equipement_index');
        }

        if (!$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }

            return $this->redirectToRoute('equipement_index');
        }

        $condition = $dto->condition;
        $prix = (float) str_replace(',', '.', (string) $dto->prix);
        $quantite = (int) $dto->quantite;
        $warrantyEnabled = $dto->warranty_enabled === '1';
        $warrantyDuration = trim((string) ($dto->warranty_duration ?? ''));

        $descParts = [];
        if ($equipement->getType()) {
            $descParts[] = 'Type : ' . $equipement->getType();
        }
        if ($equipement->getMarque()) {
            $descParts[] = 'Marque : ' . $equipement->getMarque();
        }
        if ($equipement->getModele()) {
            $descParts[] = 'Modèle : ' . $equipement->getModele();
        }
        $descParts[] = 'Condition : ' . ($condition === 'NEUF' ? 'Neuf' : 'Occasion');
        if ($condition === 'OCCASION' && $dto->used_value !== null && trim((string) $dto->used_value) !== '') {
            $uv = (int) $dto->used_value;
            $unitLabel = $dto->used_unit === 'ans' ? 'an(s)' : 'mois';
            $descParts[] = 'Utilisation : '.$uv.' '.$unitLabel;
        }
        if ($warrantyEnabled) {
            $descParts[] = 'Garantie : ' . $warrantyDuration;
        }

        $imageFilename = '';
        $img = (string) ($equipement->getImageUrl() ?? '');
        if ($img !== '') {
            $imageFilename = basename($img);
        }

        $produit = new Produits();
        $produit->setNom((string) ($equipement->getNom() ?? 'Équipement'));
        $produit->setCategorie('Équipement');
        $produit->setPrixUnitaire($prix);
        $produit->setOrigine(ProduitsRepository::ORIGINE_BOUTIQUE_AGRICULTEUR);
        $produit->setActive(true);
        $produit->setQuantite($quantite);
        $produit->setIdFournisseur($uid);
        $produit->setEquipementId($equipementId);
        $produit->setImage($imageFilename);
        $produit->setDescription($descParts !== [] ? implode(' · ', $descParts) : null);

        $this->entityManager->persist($produit);
        $this->entityManager->flush();

        $this->addFlash('success', 'Équipement publié dans votre boutique.');
        return $this->redirectToRoute('boutique_index');
    }

    #[Route('/produit/{id}/modifier', name: 'boutique_produit_modifier_get_redirect', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function modifierGetRedirect(): Response
    {
        if (($blocked = $this->redirectIfBanned()) instanceof Response) {
            return $blocked;
        }
        return $this->redirectToRoute('boutique_index');
    }

    #[Route('/produit/{id}/formulaire-modifier', name: 'boutique_produit_modifier_fragment', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function modifierFragment(int $id): Response
    {
        if (($blocked = $this->redirectIfBanned()) instanceof Response) {
            return $blocked;
        }
        [$produit, $culture, $form] = $this->prepareProduitEdit($id);

        return $this->render('marketplace/boutique/_edit_form.html.twig', [
            'form' => $form->createView(),
            'produit' => $produit,
            'culture' => $culture,
            'cancel_is_modal' => true,
            'is_equipement' => $produit->getEquipementId() !== null,
        ]);
    }

    #[Route('/produit/{id}/modifier', name: 'boutique_produit_modifier', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function modifier(int $id, Request $request): Response
    {
        if (($blocked = $this->redirectIfBanned()) instanceof Response) {
            return $blocked;
        }
        [$produit, $culture, $form] = $this->prepareProduitEdit($id);
        $form->handleRequest($request);

        $fromModal = (bool) $request->request->get('_from_boutique_modal');

        if ($form->isSubmitted() && $form->isValid()) {
            if ($produit->getEquipementId() !== null) {
                $produit->setCategorie('Équipement');
            } else {
                $this->applyCategorieEtUnite($produit, (string) $form->get('uniteVente')->getData());
            }
            $this->entityManager->flush();
            $this->addFlash('success', 'Annonce mise à jour.');

            if ($fromModal && $request->isXmlHttpRequest()) {
                return $this->json([
                    'ok' => true,
                    'redirect' => $this->generateUrl('boutique_index'),
                ]);
            }

            return $this->redirectToRoute('boutique_index');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            if ($fromModal && $request->isXmlHttpRequest()) {
                return $this->render('marketplace/boutique/_edit_form.html.twig', [
                    'form' => $form->createView(),
                    'produit' => $produit,
                    'culture' => $culture,
                    'cancel_is_modal' => true,
                    'is_equipement' => $produit->getEquipementId() !== null,
                ], new Response('', 422));
            }

            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }

            return $this->redirectToRoute('boutique_index');
        }

        return $this->redirectToRoute('boutique_index');
    }

    /**
     * @return array{0: Produits, 1: ?Culture, 2: \Symfony\Component\Form\FormInterface}
     */
    private function prepareProduitEdit(int $id): array
    {
        $produit = $this->produitsRepository->find($id);
        if (!$produit || !$this->isBoutiqueProductOwnedByUser($produit)) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        $culture = $produit->getCultureId()
            ? $this->entityManager->find(Culture::class, $produit->getCultureId())
            : null;

        $isEquipement = $produit->getEquipementId() !== null;

        $form = $this->createForm(BoutiqueCultureProduitType::class, $produit, [
            'lock_nom' => $culture !== null,
            'is_equipement' => $isEquipement,
        ]);

        return [$produit, $culture, $form];
    }

    #[Route('/produit/{id}/supprimer', name: 'boutique_produit_supprimer', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function supprimer(int $id, Request $request): Response
    {
        if (($blocked = $this->redirectIfBanned()) instanceof Response) {
            return $blocked;
        }
        $produit = $this->produitsRepository->find($id);
        if (!$produit || !$this->isBoutiqueProductOwnedByUser($produit)) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        if (!$this->isCsrfTokenValid('boutique_supprimer'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('boutique_index');
        }

        $this->entityManager->remove($produit);
        $this->entityManager->flush();
        $this->addFlash('success', 'Annonce supprimée.');

        return $this->redirectToRoute('boutique_index');
    }

    #[Route('/produit/{id}/visible', name: 'boutique_produit_basculer', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function basculerVisibilite(int $id, Request $request): Response
    {
        if (($blocked = $this->redirectIfBanned()) instanceof Response) {
            return $blocked;
        }
        $produit = $this->produitsRepository->find($id);
        if (!$produit || !$this->isBoutiqueProductOwnedByUser($produit)) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        if (!$this->isCsrfTokenValid('boutique_visible'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('boutique_index');
        }

        $current = $produit->getActive();
        $produit->setActive(!$current);
        $this->entityManager->flush();
        $this->addFlash('success', $produit->getActive()
            ? 'Annonce active : visible sur le marketplace public.'
            : 'Annonce masquée : elle n’apparaît plus sur le marketplace.');

        return $this->redirectToRoute('boutique_index');
    }

    private function applyCategorieEtUnite(Produits $produit, string $unite): void
    {
        $code = $produit->getCategory();
        $label = ($code !== null && isset(self::CATEGORY_LABELS[$code]))
            ? self::CATEGORY_LABELS[$code]
            : 'Produit';
        $produit->setCategorie($label.' · '.$unite);
    }

    private function assertUserOwnsCulture(Culture $culture, User $user): void
    {
        $owner = $culture->getParcelle()?->getExploitation()?->getUser();
        if ($owner && $owner->getId() === $user->getId()) {
            return;
        }
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }
        throw $this->createAccessDeniedException('Vous ne pouvez pas mettre en vente cette culture.');
    }

    private function isBoutiqueProductOwnedByUser(Produits $produit): bool
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($this->isGranted('ROLE_ADMIN')) {
            return $produit->getOrigine() === ProduitsRepository::ORIGINE_BOUTIQUE_AGRICULTEUR;
        }

        return $produit->getOrigine() === ProduitsRepository::ORIGINE_BOUTIQUE_AGRICULTEUR
            && $produit->getIdFournisseur() === (int) $user->getId();
    }

    #[Route('/ban', name: 'boutique_ban_info', methods: ['GET'])]
    public function banInfo(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('marketplace/boutique/ban.html.twig', [
            'ban_reason' => $user->getBanReason() ?? 'Compte temporairement suspendu.',
            'banned_at' => $user->getBannedAt(),
            'banned_until' => $user->getBannedUntil(),
            'is_permanent' => $user->isPermanentlyBanned(),
            'admin_contact_email' => $this->adminContactEmail,
        ]);
    }

    #[Route('/ban/contact-admin', name: 'boutique_ban_contact_admin', methods: ['POST'])]
    public function contactAdminFromBan(Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->banService->isBlocked($user)) {
            return $this->redirectToRoute('boutique_index');
        }

        if (!$this->isCsrfTokenValid('boutique_ban_contact_admin', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton invalide. Veuillez reessayer.');
            return $this->redirectToRoute('boutique_ban_info');
        }

        $subject = trim((string) $request->request->get('subject'));
        $message = trim((string) $request->request->get('message'));

        if ($subject === '' || $message === '') {
            $this->addFlash('error', 'Sujet et message sont requis.');
            return $this->redirectToRoute('boutique_ban_info');
        }

        $sent = $this->mailtrapContactService->sendBanAppeal($user, $subject, $message);
        if ($sent) {
            $this->addFlash('success', 'Votre email a ete envoye a l administrateur.');
        } else {
            $this->addFlash('error', 'Echec envoi email. Verifiez MAILTRAP_API_TOKEN (token API, pas smtp://), MAILTRAP_FROM_EMAIL et ADMIN_CONTACT_EMAIL.');
        }

        return $this->redirectToRoute('boutique_ban_info');
    }

    private function redirectIfBanned(): ?Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        if ($this->banService->isBlocked($user)) {
            return $this->redirectToRoute('boutique_ban_info');
        }

        return null;
    }
}
