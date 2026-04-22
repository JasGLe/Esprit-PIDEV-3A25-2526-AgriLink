<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Commandes;
use App\Entity\Marketplace\LigneCommande;
use App\Entity\Marketplace\Panier;
use App\Entity\Marketplace\Produits;
use App\Entity\UserManagement\User;
use App\Repository\Marketplace\PanierRepository;
use App\Repository\Marketplace\ProduitsRepository;
use App\Service\OrderNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Stripe\StripeClient;

#[Route('/marketplace/panier')]
#[IsGranted('ROLE_USER')]
class MarketplaceCartController extends AbstractController
{
    /** Livraison gratuite si sous-total strictement supérieur à ce montant (DT). */
    private const LIVRAISON_GRATUITE_SOUS_TOTAL_MIN = 99.0;

    private const FRAIS_LIVRAISON_STANDARD = 7.0;

    private const MODE_PAIEMENT_EN_LIGNE = 'en_ligne';

    private const MODE_PAIEMENT_LIVRAISON_CASH = 'livraison_cash';

    private const STRIPE_CURRENCY_DEFAULT = 'eur';

    public function __construct(
        private readonly PanierRepository $panierRepository,
        private readonly ProduitsRepository $produitsRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderNotificationService $orderNotificationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'marketplace_panier_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $uid = (int) $user->getId();
        $lignes = $this->panierRepository->findByUtilisateur($uid);

        $produitsParId = [];
        foreach ($lignes as $ligne) {
            $pid = $ligne->getIdProduit();
            if ($pid === null || isset($produitsParId[$pid])) {
                continue;
            }
            $entity = $this->produitsRepository->find($pid);
            if ($entity !== null) {
                $produitsParId[$pid] = $entity;
            }
        }

        $totalPanier = 0.0;
        foreach ($lignes as $ligne) {
            $pt = $ligne->getPrixTotal();
            if ($pt !== null) {
                $totalPanier += $pt;
            }
        }

        return $this->render('marketplace/panier/index.html.twig', [
            'lignes' => $lignes,
            'produits_panier' => $produitsParId,
            'total_panier' => round($totalPanier, 3),
            'livraison_gratuite_sous_total_min' => self::LIVRAISON_GRATUITE_SOUS_TOTAL_MIN,
            'frais_livraison_standard' => self::FRAIS_LIVRAISON_STANDARD,
            'promo_validate_url' => $this->generateUrl('marketplace_panier_promo_valider'),
        ]);
    }

    #[Route('/promo/valider', name: 'marketplace_panier_promo_valider', methods: ['POST'])]
    public function validerPromoCode(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $uid = (int) $user->getId();

        if (!$this->isCsrfTokenValid('panier_promo', (string) $request->request->get('_token'))) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Jeton de sécurité invalide.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $lignesPanier = $this->panierRepository->findByUtilisateur($uid);
        if ($lignesPanier === []) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Votre panier est vide.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $subtotal = $this->computeSubtotal($lignesPanier);
        $promo = $this->computePromoDiscount((string) $request->request->get('code'), $lignesPanier);
        $netSubtotal = round(max(0.0, $subtotal - $promo['discountAmount']), 3);
        $shipping = $this->fraisLivraisonPourSousTotal($netSubtotal);
        $total = round($netSubtotal + $shipping, 3);

        return new JsonResponse([
            'ok' => $promo['ok'],
            'message' => $promo['message'],
            'promoCode' => $promo['code'],
            'discountPercent' => $promo['discountPercent'],
            'discountAmount' => $promo['discountAmount'],
            'subtotal' => $subtotal,
            'subtotalAfterDiscount' => $netSubtotal,
            'shipping' => $shipping,
            'total' => $total,
        ], $promo['ok'] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Route('/commande', name: 'marketplace_panier_commander', methods: ['POST'])]
    public function commander(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $uid = (int) $user->getId();

        if (!$this->isCsrfTokenValid('checkout_commander', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('marketplace_panier_index');
        }

        $mode = (string) $request->request->get('mode_paiement');
        if (!\in_array($mode, [self::MODE_PAIEMENT_EN_LIGNE, self::MODE_PAIEMENT_LIVRAISON_CASH], true)) {
            $this->addFlash('error', 'Mode de paiement invalide.');

            return $this->redirectToRoute('marketplace_panier_index');
        }

        $nomComplet = trim((string) $request->request->get('nom'));
        $telephone = trim((string) $request->request->get('telephone'));
        $email = trim((string) $request->request->get('email'));
        $adresse = trim((string) $request->request->get('adresse'));
        $complement = trim((string) $request->request->get('complement'));
        $codePostal = trim((string) $request->request->get('code_postal'));
        $ville = trim((string) $request->request->get('ville'));
        $promoCodeInput = trim((string) $request->request->get('promo_code'));

        if ($email === '' && $user->getEmail()) {
            $email = trim((string) $user->getEmail());
        }

        if ($nomComplet === '' || $telephone === '' || $adresse === '' || $ville === '') {
            $this->addFlash('error', 'Merci de compléter tous les champs obligatoires.');

            return $this->redirectToRoute('marketplace_panier_index');
        }

        $lignesPanier = $this->panierRepository->findByUtilisateur($uid);
        if ($lignesPanier === []) {
            $this->addFlash('error', 'Votre panier est vide.');

            return $this->redirectToRoute('marketplace_panier_index');
        }

        [$prenom, $nomFamille] = $this->splitNomComplet($nomComplet);

        $conn = $this->entityManager->getConnection();
        $conn->beginTransaction();

        try {
            $sousTotal = 0.0;
            $quantiteTotale = 0;
            $prepared = [];
            $responsableUserIds = [];

            foreach ($lignesPanier as $lignePanier) {
                $pid = $lignePanier->getIdProduit();
                if ($pid === null) {
                    continue;
                }
                $produit = $this->produitsRepository->find($pid);
                if (!$produit || !$this->isProduitAchetableMarche($produit)) {
                    throw new \RuntimeException(sprintf('Le produit « %s » n’est plus disponible.', (string) $lignePanier->getNomProduit()));
                }
                $qty = $lignePanier->getQuantite();
                if ($qty < 1 || $qty > $produit->getQuantite()) {
                    throw new \RuntimeException(sprintf('Stock insuffisant pour « %s ».', $produit->getNom()));
                }
                $pu = $produit->getPrixUnitaire();
                $ligneTotal = round($pu * $qty, 3);
                $sousTotal += $ligneTotal;
                $quantiteTotale += $qty;
                $prepared[] = [$lignePanier, $produit, $qty, $pu, $ligneTotal];
                $sellerId = (int) ($produit->getIdFournisseur() ?? 0);
                if ($sellerId > 0) {
                    $responsableUserIds[$sellerId] = true;
                }
            }

            if ($prepared === []) {
                throw new \RuntimeException('Aucune ligne de panier valide.');
            }

            $promo = $this->computePromoDiscount($promoCodeInput, $lignesPanier);
            $netSousTotal = round(max(0.0, $sousTotal - $promo['discountAmount']), 3);
            $frais = $this->fraisLivraisonPourSousTotal($netSousTotal);
            $prixTotalCommande = round($netSousTotal + $frais, 3);

            $datePart = (new \DateTimeImmutable('today'))->format('Ymd');
            $numCommande = 'MKT-'.$datePart.'-'.strtoupper(bin2hex(random_bytes(3)));
            $status = $mode === self::MODE_PAIEMENT_EN_LIGNE
                ? 'EN_ATTENTE_PAIEMENT_CB'
                : 'EN_ATTENTE_LIVRAISON_CASH';

            $commande = new Commandes();
            $commande->setNumCommande($numCommande);
            $commande->setDateCommande(new \DateTimeImmutable('today'));
            $commande->setQuantite($quantiteTotale);
            $commande->setPrixTotal($prixTotalCommande);
            $commande->setStatus($status);
            $commande->setEmail($email !== '' ? $email : null);
            $commande->setPrenom($prenom);
            $commande->setNom($nomFamille);
            $commande->setAdresse($adresse);
            $commande->setComplement($complement !== '' ? $complement : null);
            $commande->setCodePostal($codePostal !== '' ? $codePostal : null);
            $commande->setVille($ville);
            $commande->setTelephone($telephone);
            $commande->setIdFournisseur(null);
            $commande->setPromoCodeApplied($promo['ok'] ? $promo['code'] : null);
            $commande->setPromoDiscountTotal($promo['ok'] ? $promo['discountAmount'] : null);

            $this->entityManager->persist($commande);
            $this->entityManager->flush();

            $commandeId = $commande->getId();

            foreach ($prepared as [$lignePanier, $produit, $qty, $pu, $ligneTotal]) {
                $lc = new LigneCommande();
                $lc->setIdCommande($commandeId);
                $lc->setIdProduit($produit->getId());
                $lc->setIdFournisseur($produit->getIdFournisseur());
                $lc->setNomProduit((string) $lignePanier->getNomProduit());
                $lc->setQuantite($qty);
                $lc->setPrixUnitaire($pu);
                $lc->setPrixTotal($ligneTotal);
                $this->entityManager->persist($lc);

                $produit->setQuantite($produit->getQuantite() - $qty);
                $this->entityManager->remove($lignePanier);
            }

            $this->entityManager->flush();
            $conn->commit();

            try {
                $this->orderNotificationService->notifyOrderCreated(
                    $commande,
                    $user,
                    array_keys($responsableUserIds),
                    $nomComplet
                );
            } catch (\Throwable $e) {
                // Do not block order flow if notification delivery fails.
                $this->logger->warning('Order notifications failed after order creation', [
                    'commandeId' => $commandeId,
                    'message' => $e->getMessage(),
                ]);
            }

            $request->getSession()->set('marketplace_order_confirm', [
                'numCommande' => $numCommande,
                'commande_id' => $commandeId,
                'paiement_en_ligne' => $mode === self::MODE_PAIEMENT_EN_LIGNE,
            ]);

            if ($mode === self::MODE_PAIEMENT_EN_LIGNE) {
                $redirect = $this->createStripeCheckoutRedirect($commande);
                if ($redirect instanceof Response) {
                    return $redirect;
                }
            }

            return $this->redirectToRoute('marketplace_index');
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }

            $raw = $e->getMessage();
            if ($e instanceof \RuntimeException) {
                $message = $raw;
            } elseif (str_contains($raw, 'Unknown column')) {
                $message = 'La base de données ne correspond pas au schéma attendu (colonne manquante). Vérifiez les migrations ou le schéma partagé avec l’app desktop.';
            } elseif ($this->getParameter('kernel.debug')) {
                $message = $raw;
            } else {
                $message = 'Impossible d’enregistrer la commande. Réessayez plus tard.';
            }

            $this->addFlash('error', $message);

            return $this->redirectToRoute('marketplace_panier_index');
        }
    }

    #[Route('/paiement/stripe/success/{id}', name: 'marketplace_panier_stripe_success', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function stripeSuccess(Request $request, int $id): Response
    {
        $sessionId = (string) $request->query->get('session_id', '');
        if ($sessionId === '') {
            $this->addFlash('error', 'Paiement Stripe: session manquante.');
            return $this->redirectToRoute('mes_commandes_index');
        }

        $commande = $this->entityManager->getRepository(Commandes::class)->find($id);
        if (!$commande instanceof Commandes) {
            $this->addFlash('error', 'Commande introuvable.');
            return $this->redirectToRoute('mes_commandes_index');
        }

        try {
            $client = $this->stripeClient();
            if ($client === null) {
                $this->addFlash('error', 'Stripe n’est pas configuré.');
                return $this->redirectToRoute('mes_commandes_index');
            }

            $session = $client->checkout->sessions->retrieve($sessionId, []);
            if (($session->payment_status ?? null) !== 'paid') {
                $this->addFlash('error', 'Paiement non confirmé.');
                return $this->redirectToRoute('mes_commandes_index');
            }

            // Mark as paid → move to preparation
            if ($commande->getStatus() === 'EN_ATTENTE_PAIEMENT_CB') {
                $commande->setStatus('EN_PREPARATION');
                $this->entityManager->persist($commande);
                $this->entityManager->flush();
            }

            $this->addFlash('success', 'Paiement confirmé. Votre commande est en préparation.');
            return $this->redirectToRoute('mes_commandes_index');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur Stripe: '.$e->getMessage());
            return $this->redirectToRoute('mes_commandes_index');
        }
    }

    #[Route('/paiement/stripe/cancel/{id}', name: 'marketplace_panier_stripe_cancel', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function stripeCancel(int $id): Response
    {
        $this->addFlash('error', 'Paiement en ligne annulé.');
        return $this->redirectToRoute('mes_commandes_index');
    }

    private function createStripeCheckoutRedirect(Commandes $commande): ?Response
    {
        $client = $this->stripeClient();
        if ($client === null) {
            $this->addFlash('error', 'Stripe n’est pas configuré (clé manquante ou dépendance).');
            return $this->redirectToRoute('marketplace_panier_index');
        }

        $currency = strtolower((string) ($_ENV['STRIPE_CURRENCY'] ?? self::STRIPE_CURRENCY_DEFAULT));
        $amountCents = (int) round(max(0.0, (float) $commande->getPrixTotal()) * 100);
        if ($amountCents < 50) {
            $this->addFlash('error', 'Montant trop faible pour Stripe.');
            return $this->redirectToRoute('marketplace_panier_index');
        }

        $successUrl = $this->generateUrl('marketplace_panier_stripe_success', ['id' => $commande->getId()], 0).'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl  = $this->generateUrl('marketplace_panier_stripe_cancel', ['id' => $commande->getId()], 0);

        $session = $client->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $amountCents,
                    'product_data' => [
                        'name' => 'Commande '.$commande->getNumCommande(),
                    ],
                ],
            ]],
            'metadata' => [
                'commande_id' => (string) $commande->getId(),
                'num_commande' => $commande->getNumCommande(),
            ],
        ]);

        return $this->redirect((string) $session->url);
    }

    private function stripeClient(): ?StripeClient
    {
        if (!class_exists(StripeClient::class)) {
            return null;
        }
        $secret = (string) ($_ENV['STRIPE_SECRET_KEY'] ?? '');
        if (trim($secret) === '') {
            return null;
        }

        return new StripeClient($secret);
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function splitNomComplet(string $nomComplet): array
    {
        $tok = preg_split('/\s+/u', trim($nomComplet), -1, \PREG_SPLIT_NO_EMPTY);
        if ($tok === false || $tok === []) {
            return [null, $nomComplet];
        }
        if (\count($tok) === 1) {
            return [null, $tok[0]];
        }

        return [$tok[0], implode(' ', \array_slice($tok, 1))];
    }

    private function fraisLivraisonPourSousTotal(float $sousTotal): float
    {
        return $sousTotal > self::LIVRAISON_GRATUITE_SOUS_TOTAL_MIN ? 0.0 : self::FRAIS_LIVRAISON_STANDARD;
    }

    /**
     * @param Panier[] $lignesPanier
     */
    private function computeSubtotal(array $lignesPanier): float
    {
        $subtotal = 0.0;
        foreach ($lignesPanier as $lignePanier) {
            $pt = $lignePanier->getPrixTotal();
            if ($pt !== null) {
                $subtotal += $pt;
            }
        }

        return round($subtotal, 3);
    }

    /**
     * @param Panier[] $lignesPanier
     * @return array{ok: bool, message: string, code: ?string, discountPercent: float, discountAmount: float}
     */
    private function computePromoDiscount(string $rawCode, array $lignesPanier): array
    {
        $code = mb_strtoupper(trim($rawCode));
        $today = new \DateTimeImmutable('today');
        if ($code === '') {
            return [
                'ok' => false,
                'message' => 'Saisissez un code promo.',
                'code' => null,
                'discountPercent' => 0.0,
                'discountAmount' => 0.0,
            ];
        }

        $eligibleSubtotal = 0.0;
        $discountPercent = null;
        foreach ($lignesPanier as $lignePanier) {
            $pid = $lignePanier->getIdProduit();
            if ($pid === null) {
                continue;
            }
            $produit = $this->produitsRepository->find($pid);
            if (!$produit || !$produit->isPromoActive() || $produit->getPromoCode() !== $code) {
                continue;
            }
            if (!$produit->getActive() || $produit->getQuantite() <= 0 || $produit->getPromoDiscountPercent() === null) {
                continue;
            }
            $startAt = $produit->getPromoStartAt();
            $endAt = $produit->getPromoEndAt();
            if ($startAt === null || $endAt === null) {
                continue;
            }
            if ($today < \DateTimeImmutable::createFromInterface($startAt)->setTime(0, 0, 0)) {
                continue;
            }
            if ($today > \DateTimeImmutable::createFromInterface($endAt)->setTime(23, 59, 59)) {
                continue;
            }

            $discountPercent = $discountPercent ?? (float) $produit->getPromoDiscountPercent();
            $lineTotal = $lignePanier->getPrixTotal() ?? 0.0;
            $eligibleSubtotal += max(0.0, $lineTotal);
        }

        if ($eligibleSubtotal <= 0 || $discountPercent === null) {
            return [
                'ok' => false,
                'message' => 'Code invalide ou non applicable aux produits du panier.',
                'code' => null,
                'discountPercent' => 0.0,
                'discountAmount' => 0.0,
            ];
        }

        $discountPercent = min(100.0, max(1.0, $discountPercent));
        $discountAmount = round($eligibleSubtotal * ($discountPercent / 100), 3);

        return [
            'ok' => true,
            'message' => sprintf('Code appliqué : -%s%% sur les produits éligibles.', rtrim(rtrim(number_format($discountPercent, 2, '.', ''), '0'), '.')),
            'code' => $code,
            'discountPercent' => $discountPercent,
            'discountAmount' => $discountAmount,
        ];
    }

    /**
     * Aperçu JSON pour le panier popup (marketplace).
     */
    #[Route('/apercu', name: 'marketplace_panier_apercu', methods: ['GET'])]
    public function apercu(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return new JsonResponse($this->buildPanierPreviewPayload((int) $user->getId()));
    }

    private function jsonPanierEtat(int $userId, bool $ok, string $message, int $status = Response::HTTP_OK): JsonResponse
    {
        $preview = $this->buildPanierPreviewPayload($userId);

        return new JsonResponse([
            'ok' => $ok,
            'message' => $message,
            'count' => $preview['count'],
            'lines' => $preview['lines'],
            'totalPrix' => $preview['totalPrix'],
        ], $status);
    }

    /**
     * @return array{count: int, lines: list<array{produitId: int|null, nom: string|null, quantite: int, prixTotal: float|null}>, totalPrix: float}
     */
    private function buildPanierPreviewPayload(int $userId): array
    {
        $lignes = $this->panierRepository->findByUtilisateur($userId);
        $lines = [];
        $totalPrix = 0.0;
        foreach ($lignes as $ligne) {
            $pt = $ligne->getPrixTotal();
            if ($pt !== null) {
                $totalPrix += $pt;
            }
            $lines[] = [
                'produitId' => $ligne->getIdProduit(),
                'nom' => $ligne->getNomProduit(),
                'quantite' => $ligne->getQuantite(),
                'prixTotal' => $pt,
            ];
        }

        return [
            'count' => $this->panierRepository->countLignesProduitsPourUtilisateur($userId),
            'lines' => $lines,
            'totalPrix' => round($totalPrix, 3),
        ];
    }

    #[Route('/ligne/quantite', name: 'marketplace_panier_ligne_quantite', methods: ['POST'])]
    public function ajusterLigneQuantite(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $uid = (int) $user->getId();

        if (!$this->isCsrfTokenValid('panier_quantite', (string) $request->request->get('_token'))) {
            return $this->jsonPanierEtat($uid, false, 'Jeton de sécurité invalide.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $produitId = (int) $request->request->get('produitId');
        $delta = (int) $request->request->get('delta');
        if ($produitId < 1 || ($delta !== 1 && $delta !== -1)) {
            return $this->jsonPanierEtat($uid, false, 'Requête invalide.', Response::HTTP_BAD_REQUEST);
        }

        $ligne = $this->panierRepository->findLigneByUtilisateurEtProduit($uid, $produitId);
        if (!$ligne) {
            return $this->jsonPanierEtat($uid, false, 'Ligne introuvable.', Response::HTTP_NOT_FOUND);
        }

        $produit = $this->produitsRepository->find($produitId);
        $newQty = $ligne->getQuantite() + $delta;

        if ($newQty < 1) {
            $this->entityManager->remove($ligne);
            $this->entityManager->flush();

            return $this->jsonPanierEtat($uid, true, '');
        }

        if (!$produit) {
            $this->entityManager->remove($ligne);
            $this->entityManager->flush();

            return $this->jsonPanierEtat($uid, true, 'Ce produit n’est plus disponible.');
        }

        if ($delta === 1 && !$this->isProduitAchetableMarche($produit)) {
            return $this->jsonPanierEtat($uid, false, 'Ce produit n’est plus disponible à l’achat.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($newQty > $produit->getQuantite()) {
            return $this->jsonPanierEtat($uid, false, 'Stock insuffisant.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $prixUnitaire = $produit->getPrixUnitaire();
        $ligne->setQuantite($newQty);
        $ligne->setPrixTotal($prixUnitaire * $newQty);
        $this->entityManager->flush();

        return $this->jsonPanierEtat($uid, true, '');
    }

    #[Route('/ligne/supprimer', name: 'marketplace_panier_ligne_supprimer', methods: ['POST'])]
    public function supprimerLigne(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $uid = (int) $user->getId();

        if (!$this->isCsrfTokenValid('panier_supprimer', (string) $request->request->get('_token'))) {
            return $this->jsonPanierEtat($uid, false, 'Jeton de sécurité invalide.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $produitId = (int) $request->request->get('produitId');
        if ($produitId < 1) {
            return $this->jsonPanierEtat($uid, false, 'Requête invalide.', Response::HTTP_BAD_REQUEST);
        }

        $ligne = $this->panierRepository->findLigneByUtilisateurEtProduit($uid, $produitId);
        if ($ligne) {
            $this->entityManager->remove($ligne);
            $this->entityManager->flush();
        }

        return $this->jsonPanierEtat($uid, true, '');
    }

    #[Route('/ajouter/{id}', name: 'marketplace_panier_ajouter', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function ajouter(int $id, Request $request): JsonResponse|Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $uid = (int) $user->getId();

        if (!$this->isCsrfTokenValid('panier_ajout', (string) $request->request->get('_token'))) {
            return $this->panierJsonOrRedirect($request, false, 'Jeton de sécurité invalide.', $uid);
        }

        $produit = $this->produitsRepository->find($id);
        if (!$produit) {
            return $this->panierJsonOrRedirect($request, false, 'Produit introuvable.', $uid);
        }

        if (!$this->isProduitAchetableMarche($produit)) {
            return $this->panierJsonOrRedirect($request, false, 'Ce produit n’est pas disponible à l’achat.', $uid);
        }

        if ($produit->getIdFournisseur() === $uid) {
            return $this->panierJsonOrRedirect($request, false, 'Vous ne pouvez pas acheter votre propre produit.', $uid);
        }

        $ligne = $this->panierRepository->findLigneByUtilisateurEtProduit($uid, $id);
        $nouvelleQuantite = $ligne ? $ligne->getQuantite() + 1 : 1;

        if ($nouvelleQuantite > $produit->getQuantite()) {
            return $this->panierJsonOrRedirect($request, false, 'Stock insuffisant.', $uid);
        }

        $prixUnitaire = $produit->getPrixUnitaire();
        if ($ligne) {
            $ligne->setQuantite($nouvelleQuantite);
            $ligne->setPrixTotal($prixUnitaire * $nouvelleQuantite);
        } else {
            $ligne = new Panier();
            $ligne->setIdPersonne($uid);
            $ligne->setIdProduit($id);
            $ligne->setNomProduit($produit->getNom());
            $ligne->setQuantite(1);
            $ligne->setPrixTotal($prixUnitaire);
            $ligne->setDateAjout(new \DateTime());
            $this->entityManager->persist($ligne);
        }

        $this->entityManager->flush();

        return $this->panierJsonOrRedirect($request, true, 'Produit ajouté au panier.', $uid);
    }

    private function isProduitAchetableMarche(Produits $p): bool
    {
        if ($p->getOrigine() !== ProduitsRepository::ORIGINE_BOUTIQUE_AGRICULTEUR) {
            return false;
        }

        if ($p->isRental()) {
            return false;
        }

        return $p->getActive() && $p->getQuantite() > 0;
    }

    /**
     * @return JsonResponse|Response
     */
    private function panierJsonOrRedirect(Request $request, bool $ok, string $message, int $userId): JsonResponse|Response
    {
        $wantsJson = $request->isXmlHttpRequest()
            || str_contains((string) $request->headers->get('Accept'), 'application/json');

        if ($wantsJson) {
            $status = $ok ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY;
            $preview = $this->buildPanierPreviewPayload($userId);

            return new JsonResponse([
                'ok' => $ok,
                'message' => $message,
                'count' => $preview['count'],
                'lines' => $preview['lines'],
                'totalPrix' => $preview['totalPrix'],
            ], $status);
        }

        if ($ok) {
            $this->addFlash('success', $message);
        } else {
            $this->addFlash('error', $message);
        }

        return $this->redirectToRoute('marketplace_index');
    }
}
