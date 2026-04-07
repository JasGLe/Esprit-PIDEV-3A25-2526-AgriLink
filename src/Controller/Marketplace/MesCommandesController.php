<?php

namespace App\Controller\Marketplace;

use App\Entity\CancellationRequests;
use App\Entity\Marketplace\Commandes;
use App\Entity\UserManagement\User;
use App\Form\Marketplace\CommandeLivraisonEditType;
use App\Repository\CancellationRequestsRepository;
use App\Repository\Marketplace\CommandesRepository;
use App\Repository\Marketplace\LigneCommandeRepository;
use App\Repository\UserManagement\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mes-commandes', name: 'mes_commandes_')]
#[IsGranted('ROLE_USER')]
class MesCommandesController extends AbstractController
{
    /** @var array<string, string> */
    private const STATUTS_COMMANDE_MARCHE = [
        'EN_ATTENTE_PAIEMENT_CB' => 'En attente paiement (CB)',
        'EN_ATTENTE_LIVRAISON_CASH' => 'En attente paiement à la livraison',
        'EN_PREPARATION' => 'En préparation',
        'EXPEDIEE' => 'Expédiée',
        'LIVREE' => 'Livrée',
        'ANNULEE' => 'Annulée',
    ];

    /** Vendeur et admin : suite logistique uniquement (pas les statuts « en attente paiement »). */
    private const STATUTS_VENDEUR_ACTION = [
        'EN_PREPARATION' => 'En préparation',
        'EXPEDIEE' => 'Expédiée',
        'LIVREE' => 'Livrée',
        'ANNULEE' => 'Annulée',
    ];

    public function __construct(
        private readonly CommandesRepository $commandesRepository,
        private readonly LigneCommandeRepository $ligneCommandeRepository,
        private readonly UserRepository $userRepository,
        private readonly CancellationRequestsRepository $cancellationRequestsRepository,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN')) {
            $commandes = $this->commandesRepository->findAllMarketplaceCommandes();
            $counts = $this->countBuckets($commandes);
            $commandeIds = $this->commandeIdsList($commandes);
            $pendingMap = $this->cancellationRequestsRepository->findPendingCommandeIdMap($commandeIds);

            return $this->render('marketplace/mes_commandes/index.html.twig', [
                'mes_commandes_mode' => 'admin',
                'commandes' => $commandes,
                'montants_vendeur' => [],
                'vendeurs_par_commande' => $this->buildVendeurLabelsForCommandesAdmin($commandes),
                'count_total' => $counts['total'],
                'count_en_cours' => $counts['en_cours'],
                'count_livrees' => $counts['livrees'],
                'count_annulations_attente' => $this->cancellationRequestsRepository->countPendingForCommandeIds($commandeIds),
                'pending_cancellation_map' => $pendingMap,
                'buyer_cancel_eligible_map' => [],
                'statut_choices_seller' => null,
                'statut_choices_by_commande' => $this->adminStatutChoicesByCommandes($commandes),
                'deletable_commande_ids' => $this->buildDeletableCommandeIds($commandes, $user, 'admin'),
            ]);
        }

        if ($this->isMarcheAcheteur()) {
            $commandes = $this->commandesRepository->findForMarketplaceClient($user);
            $counts = $this->countBuckets($commandes);
            $commandeIds = $this->commandeIdsList($commandes);
            $pendingMap = $this->cancellationRequestsRepository->findPendingCommandeIdMap($commandeIds);

            return $this->render('marketplace/mes_commandes/index.html.twig', [
                'mes_commandes_mode' => 'acheteur',
                'commandes' => $commandes,
                'montants_vendeur' => [],
                'count_total' => $counts['total'],
                'count_en_cours' => $counts['en_cours'],
                'count_livrees' => $counts['livrees'],
                'count_annulations_attente' => 0,
                'pending_cancellation_map' => $pendingMap,
                'buyer_cancel_eligible_map' => $this->buildBuyerCancelEligibleMap($commandes, $pendingMap),
                'statut_choices_seller' => null,
                'statut_choices_by_commande' => $this->acheteurStatutChoicesByCommandes($commandes),
                'deletable_commande_ids' => $this->buildDeletableCommandeIds($commandes, $user, 'acheteur'),
            ]);
        }

        if ($this->isMesCommandesVendeurContext()) {
            $sellerId = (int) $user->getId();
            $commandes = $this->commandesRepository->findForMarketplaceVendeur($sellerId);
            $counts = $this->countBuckets($commandes);
            $montants = [];
            foreach ($commandes as $c) {
                $montants[$c->getId()] = $this->ligneCommandeRepository->sumMontantForFournisseurOnCommande(
                    $c->getId(),
                    $sellerId
                );
            }

            $commandeIds = $this->commandeIdsList($commandes);
            $pendingMap = $this->cancellationRequestsRepository->findPendingCommandeIdMap($commandeIds);

            return $this->render('marketplace/mes_commandes/index.html.twig', [
                'mes_commandes_mode' => 'vendeur',
                'commandes' => $commandes,
                'montants_vendeur' => $montants,
                'count_total' => $counts['total'],
                'count_en_cours' => $counts['en_cours'],
                'count_livrees' => $counts['livrees'],
                'count_annulations_attente' => $this->cancellationRequestsRepository->countPendingForCommandeIds($commandeIds),
                'pending_cancellation_map' => $pendingMap,
                'buyer_cancel_eligible_map' => [],
                'statut_choices_seller' => self::STATUTS_VENDEUR_ACTION,
                'statut_choices_by_commande' => [],
                'deletable_commande_ids' => $this->buildDeletableCommandeIds($commandes, $user, 'vendeur'),
            ]);
        }

        $commandes = $this->commandesRepository->findForMarketplaceClient($user);
        $counts = $this->countBuckets($commandes);

        $commandeIds = $this->commandeIdsList($commandes);
        $pendingMap = $this->cancellationRequestsRepository->findPendingCommandeIdMap($commandeIds);

        return $this->render('marketplace/mes_commandes/index.html.twig', [
            'mes_commandes_mode' => 'acheteur',
            'commandes' => $commandes,
            'montants_vendeur' => [],
            'count_total' => $counts['total'],
            'count_en_cours' => $counts['en_cours'],
            'count_livrees' => $counts['livrees'],
            'count_annulations_attente' => 0,
            'pending_cancellation_map' => $pendingMap,
            'buyer_cancel_eligible_map' => $this->buildBuyerCancelEligibleMap($commandes, $pendingMap),
            'statut_choices_seller' => null,
            'statut_choices_by_commande' => $this->acheteurStatutChoicesByCommandes($commandes),
            'deletable_commande_ids' => $this->buildDeletableCommandeIds($commandes, $user, 'acheteur'),
        ]);
    }

    #[Route('/{id}/edit-modal', name: 'edit_modal', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function editModal(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN')) {
            $commande = $this->findCommandeAdmin($id);
        } elseif ($this->isMarcheAcheteur()) {
            $commande = $this->findOwnedCommandeAcheteur($id, $user);
        } elseif ($this->isMesCommandesVendeurContext()) {
            $commande = $this->findAccessibleCommandeVendeur($id, (int) $user->getId());
        } else {
            $commande = $this->findOwnedCommandeAcheteur($id, $user);
        }

        $form = $this->createForm(CommandeLivraisonEditType::class, $commande);

        return $this->render('marketplace/mes_commandes/_edit_modal_fragment.html.twig', [
            'commande' => $commande,
            'form' => $form,
            'mes_commandes_mode' => $this->resolveMesCommandesListMode(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN')) {
            $commande = $this->findCommandeAdmin($id);
        } elseif ($this->isMarcheAcheteur()) {
            $commande = $this->findOwnedCommandeAcheteur($id, $user);
        } elseif ($this->isMesCommandesVendeurContext()) {
            $commande = $this->findAccessibleCommandeVendeur($id, (int) $user->getId());
        } else {
            $commande = $this->findOwnedCommandeAcheteur($id, $user);
        }

        $form = $this->createForm(CommandeLivraisonEditType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $this->commandesRepository->save($commande, true);
                if ($this->wantsOrderEditAjaxResponse($request)) {
                    return new JsonResponse([
                        'ok' => true,
                        'message' => 'Coordonnées de livraison mises à jour.',
                    ]);
                }
                $this->addFlash('success', 'Coordonnées de livraison mises à jour.');

                return $this->redirectToRoute('mes_commandes_show', ['id' => $id]);
            }
            if ($this->wantsOrderEditAjaxResponse($request)) {
                return new Response(
                    $this->renderView('marketplace/mes_commandes/_edit_modal_fragment.html.twig', [
                        'commande' => $commande,
                        'form' => $form,
                        'mes_commandes_mode' => $this->resolveMesCommandesListMode(),
                    ]),
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        return $this->render('marketplace/mes_commandes/edit.html.twig', [
            'commande' => $commande,
            'form' => $form,
            'mes_commandes_mode' => $this->resolveMesCommandesListMode(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('delete_commande_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN')) {
            $commande = $this->findCommandeAdmin($id);
            $mode = 'admin';
        } elseif ($this->isMarcheAcheteur()) {
            $commande = $this->findOwnedCommandeAcheteur($id, $user);
            $mode = 'acheteur';
        } elseif ($this->isMesCommandesVendeurContext()) {
            $commande = $this->findAccessibleCommandeVendeur($id, (int) $user->getId());
            $mode = 'vendeur';
        } else {
            $commande = $this->findOwnedCommandeAcheteur($id, $user);
            $mode = 'acheteur';
        }

        if (!$this->commandeMayBeDeleted($commande, $user, $mode)) {
            $this->addFlash(
                'error',
                $mode === 'admin'
                    ? 'La suppression n’est possible que pour une commande livrée ou annulée.'
                    : 'Cette commande ne peut pas être supprimée (livrée, ou panier multi-producteurs).'
            );

            return $this->redirectToRoute('mes_commandes_index');
        }

        foreach ($this->ligneCommandeRepository->findBy(['idCommande' => $commande->getId()]) as $ligne) {
            $this->ligneCommandeRepository->remove($ligne, false);
        }
        $this->commandesRepository->remove($commande, true);
        $this->addFlash('success', 'La commande a été supprimée.');

        return $this->redirectToRoute('mes_commandes_index');
    }

    #[Route('/{id}/demande-annulation', name: 'cancel_request', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function cancelRequest(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('cancel_request_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $commande = $this->findOwnedCommandeAcheteur($id, $user);

        $pendingMap = $this->cancellationRequestsRepository->findPendingCommandeIdMap([$id]);
        if (!$this->buyerMayRequestCancellation($commande, isset($pendingMap[$id]))) {
            $this->addFlash('error', 'Une demande d’annulation n’est pas possible (délai de 48 h dépassé, commande non éligible ou demande déjà en cours).');

            return $this->redirectToRoute('mes_commandes_index');
        }

        $req = new CancellationRequests();
        $req->setCommandeId($commande->getId());
        $req->setNumCommande($commande->getNumCommande());
        $req->setRequestedByUserId((int) $user->getId());
        $req->setRequestedByEmail($user->getEmail());
        $req->setRequestedByName(trim((string) $user->getNom()));
        $req->setRequestedAt(new \DateTimeImmutable());
        $req->setStatus(CancellationRequestsRepository::STATUS_PENDING);
        $this->cancellationRequestsRepository->save($req, true);
        $this->addFlash('success', 'Votre demande d’annulation a été envoyée. Un administrateur ou le vendeur pourra l’accepter ou la refuser.');

        return $this->redirectToRoute('mes_commandes_index');
    }

    #[Route('/{id}/annulation/accepter', name: 'cancel_approve', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function cancelApprove(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('cancel_approve_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $commande = $this->resolveCommandeForCancellationModeration($user, $id);

        $cr = $this->cancellationRequestsRepository->findOnePendingByCommandeId($commande->getId());
        if ($cr === null) {
            $this->addFlash('error', 'Aucune demande d’annulation en attente pour cette commande.');

            return $this->redirectToRoute('mes_commandes_index');
        }

        $this->applyCancellationApproval($commande, $cr, $user);
        $this->addFlash('success', 'La commande a été annulée (statut : Annulée).');

        return $this->redirectToRoute('mes_commandes_index');
    }

    #[Route('/{id}/annulation/refuser', name: 'cancel_reject', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function cancelReject(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('cancel_reject_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $commande = $this->resolveCommandeForCancellationModeration($user, $id);

        $cr = $this->cancellationRequestsRepository->findOnePendingByCommandeId($commande->getId());
        if ($cr === null) {
            $this->addFlash('error', 'Aucune demande d’annulation en attente pour cette commande.');

            return $this->redirectToRoute('mes_commandes_index');
        }

        $cr->setStatus(CancellationRequestsRepository::STATUS_REJECTED);
        $cr->setHandledByUserId((int) $user->getId());
        $cr->setHandledAt(new \DateTimeImmutable());
        $this->cancellationRequestsRepository->save($cr, true);
        $this->addFlash('success', 'La demande d’annulation a été refusée. La commande reste inchangée.');

        return $this->redirectToRoute('mes_commandes_index');
    }

    #[Route('/{id}/detail-modal', name: 'detail_modal', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function detailModal(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('marketplace/mes_commandes/_detail_modal_fragment.html.twig', array_merge(
            $this->buildOrderDetailVars($id, $user),
            ['statut_redirect' => 'index']
        ));
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('marketplace/mes_commandes/show.html.twig', $this->buildOrderDetailVars($id, $user));
    }

    #[Route('/{id}/statut', name: 'statut', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function updateStatut(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('mes_commandes_statut', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $new = trim((string) $request->request->get('status'));
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN')) {
            $commande = $this->findCommandeAdmin($id);
            $allowed = array_keys(self::STATUTS_VENDEUR_ACTION);
        } elseif ($this->isMarcheAcheteur()) {
            $commande = $this->findOwnedCommandeAcheteur($id, $user);
            $allowed = array_keys($this->acheteurStatutChoices($commande));
        } elseif ($this->isMesCommandesVendeurContext()) {
            $commande = $this->findAccessibleCommandeVendeur($id, (int) $user->getId());
            $allowed = array_keys(self::STATUTS_VENDEUR_ACTION);
        } else {
            $commande = $this->findOwnedCommandeAcheteur($id, $user);
            $allowed = array_keys($this->acheteurStatutChoices($commande));
        }

        if (!\in_array($new, $allowed, true)) {
            $this->addFlash('error', 'Statut invalide ou non autorisé.');

            return $this->redirectAfterMesCommandesStatut($request, $id);
        }

        if ($new === $commande->getStatus()) {
            $this->addFlash('info', 'Le statut est déjà celui sélectionné.');

            return $this->redirectAfterMesCommandesStatut($request, $id);
        }

        $commande->setStatus($new);
        if ($new === 'ANNULEE') {
            $this->closePendingCancellationAsApproved($commande, $user);
        }
        $this->commandesRepository->save($commande, true);
        $this->addFlash('success', 'Statut de la commande mis à jour.');

        return $this->redirectAfterMesCommandesStatut($request, $id);
    }

    #[Route('/{id}/pdf', name: 'pdf', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function pdf(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN')) {
            $this->findCommandeAdmin($id);
        } elseif ($this->isMarcheAcheteur()) {
            $this->findOwnedCommandeAcheteur($id, $user);
        } elseif ($this->isMesCommandesVendeurContext()) {
            $this->findAccessibleCommandeVendeur($id, (int) $user->getId());
        } else {
            $this->findOwnedCommandeAcheteur($id, $user);
        }

        $this->addFlash('info', 'Export PDF disponible prochainement.');

        return $this->redirectToRoute('mes_commandes_index');
    }

    private function isMarcheAcheteur(): bool
    {
        return $this->isGranted('ROLE_AGRIPLUS');
    }

    /**
     * Commandes « Mes commandes » en tant que vendeur (lignes dont id_fournisseur = utilisateur).
     * AgriPlus est traité comme acheteur marketplace avant cette branche (voir index / resolveMesCommandesListMode).
     */
    private function isMesCommandesVendeurContext(): bool
    {
        return $this->isGranted('ROLE_AGRICULTEUR') || $this->isGranted('ROLE_FOURNISSEUR');
    }

    /**
     * @param list<Commandes> $commandes
     *
     * @return array{total: int, en_cours: int, livrees: int}
     */
    private function countBuckets(array $commandes): array
    {
        $livrees = 0;
        $enCours = 0;
        foreach ($commandes as $c) {
            $cat = $this->categorizeStatus($c->getStatus());
            if ($cat === 'livree') {
                ++$livrees;
            } elseif ($cat !== 'annulee') {
                ++$enCours;
            }
        }

        return [
            'total' => \count($commandes),
            'en_cours' => $enCours,
            'livrees' => $livrees,
        ];
    }

    private function findOwnedCommandeAcheteur(int $id, User $user): Commandes
    {
        $commande = $this->commandesRepository->find($id);
        if (!$commande instanceof Commandes) {
            throw $this->createNotFoundException();
        }
        $uEmail = strtolower(trim((string) $user->getEmail()));
        $cEmail = strtolower(trim((string) $commande->getEmail()));
        if ($uEmail === '' || $cEmail !== $uEmail) {
            throw $this->createNotFoundException();
        }

        return $commande;
    }

    private function findAccessibleCommandeVendeur(int $id, int $sellerUserId): Commandes
    {
        $commande = $this->commandesRepository->find($id);
        if (!$commande instanceof Commandes) {
            throw $this->createNotFoundException();
        }
        $lignes = $this->ligneCommandeRepository->findByCommandeAndFournisseur($commande->getId(), $sellerUserId);
        if ($lignes === []) {
            throw $this->createNotFoundException();
        }

        return $commande;
    }

    /**
     * Admin ou vendeur (agriculteur / fournisseur avec au moins une ligne sur la commande).
     */
    private function resolveCommandeForCancellationModeration(User $user, int $id): Commandes
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->findCommandeAdmin($id);
        }
        if ($this->isMesCommandesVendeurContext()) {
            return $this->findAccessibleCommandeVendeur($id, (int) $user->getId());
        }

        throw $this->createAccessDeniedException();
    }

    /**
     * Acceptation d’une demande d’annulation : commande → ANNULEE en base + demande marquée approuvée.
     */
    private function applyCancellationApproval(Commandes $commande, CancellationRequests $cr, User $user): void
    {
        $commande->setStatus('ANNULEE');
        $cr->setStatus(CancellationRequestsRepository::STATUS_APPROVED);
        $cr->setHandledByUserId((int) $user->getId());
        $cr->setHandledAt(new \DateTimeImmutable());

        $em = $this->commandesRepository->getEntityManager();
        $em->persist($commande);
        $em->persist($cr);
        $em->flush();
    }

    /**
     * @return 'livree'|'annulee'|'encours'
     */
    private function categorizeStatus(string $status): string
    {
        $s = strtolower($status);
        if (str_contains($s, 'annul')) {
            return 'annulee';
        }
        if (str_contains($s, 'livr') || str_contains($s, 'livré') || str_contains($s, 'termine') || str_contains($s, 'reception')) {
            return 'livree';
        }

        return 'encours';
    }

    /**
     * @param list<Commandes> $commandes
     *
     * @return list<int>
     */
    private function commandeIdsList(array $commandes): array
    {
        return array_map(static fn (Commandes $c) => $c->getId(), $commandes);
    }

    /**
     * @param list<Commandes>     $commandes
     * @param array<int, true>    $pendingMap
     *
     * @return array<int, bool>
     */
    private function buildBuyerCancelEligibleMap(array $commandes, array $pendingMap): array
    {
        $out = [];
        foreach ($commandes as $c) {
            $id = $c->getId();
            $out[$id] = $this->buyerMayRequestCancellation($c, isset($pendingMap[$id]));
        }

        return $out;
    }

    private function buyerMayRequestCancellation(Commandes $commande, bool $hasPendingRequest): bool
    {
        if ($hasPendingRequest) {
            return false;
        }
        if (!$this->isWithinBuyerCancellationWindow($commande)) {
            return false;
        }
        if ($this->categorizeStatus($commande->getStatus()) !== 'encours') {
            return false;
        }
        $st = strtoupper($commande->getStatus());
        if (str_contains($st, 'EXPEDI')) {
            return false;
        }

        return true;
    }

    private function isWithinBuyerCancellationWindow(Commandes $commande): bool
    {
        $start = \DateTimeImmutable::createFromInterface($commande->getDateCommande());
        $deadline = $start->modify('+48 hours');

        return new \DateTimeImmutable() <= $deadline;
    }

    private function closePendingCancellationAsApproved(Commandes $commande, User $user): void
    {
        $cr = $this->cancellationRequestsRepository->findOnePendingByCommandeId($commande->getId());
        if ($cr === null) {
            return;
        }
        $cr->setStatus(CancellationRequestsRepository::STATUS_APPROVED);
        $cr->setHandledByUserId((int) $user->getId());
        $cr->setHandledAt(new \DateTimeImmutable());
        $this->cancellationRequestsRepository->save($cr, false);
    }

    /**
     * @return array{
     *     cancellation_request_pending: bool,
     *     show_buyer_cancel_request: bool,
     *     show_cancellation_moderation: bool,
     *     buyer_cancel_order_ref: string
     * }
     */
    private function cancellationContextForCommande(Commandes $commande, string $mode): array
    {
        $pending = $this->cancellationRequestsRepository->findOnePendingByCommandeId($commande->getId()) !== null;
        $showModeration = $pending && \in_array($mode, ['admin', 'vendeur'], true);
        $showBuyerCancel = $mode === 'acheteur' && $this->buyerMayRequestCancellation($commande, $pending);

        return [
            'cancellation_request_pending' => $pending,
            'show_buyer_cancel_request' => $showBuyerCancel,
            'show_cancellation_moderation' => $showModeration,
            'buyer_cancel_order_ref' => '#CMD'.str_pad((string) $commande->getId(), 3, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * L’acheteur ne modifie plus le statut ici : annulation = demande sous 48 h, validée par admin ou vendeur.
     *
     * @return array<string, string>
     */
    private function acheteurStatutChoices(Commandes $commande): array
    {
        return [];
    }

    /**
     * @param list<Commandes> $commandes
     *
     * @return array<int, array<string, string>>
     */
    private function acheteurStatutChoicesByCommandes(array $commandes): array
    {
        $out = [];
        foreach ($commandes as $c) {
            $out[$c->getId()] = $this->acheteurStatutChoices($c);
        }

        return $out;
    }

    private function redirectAfterMesCommandesStatut(Request $request, int $id): Response
    {
        if ($request->request->get('_redirect') === 'index') {
            return $this->redirectToRoute('mes_commandes_index');
        }

        return $this->redirectToRoute('mes_commandes_show', ['id' => $id]);
    }

    /**
     * @param list<Commandes> $commandes
     *
     * @return array<int, true>
     */
    private function buildDeletableCommandeIds(array $commandes, User $user, string $mode): array
    {
        $out = [];
        foreach ($commandes as $c) {
            if ($this->commandeMayBeDeleted($c, $user, $mode)) {
                $out[$c->getId()] = true;
            }
        }

        return $out;
    }

    private function commandeMayBeDeleted(Commandes $commande, User $user, string $mode): bool
    {
        $cat = $this->categorizeStatus($commande->getStatus());

        if ($mode === 'admin') {
            return $cat === 'livree' || $cat === 'annulee';
        }

        if ($cat === 'livree') {
            return false;
        }
        if ($mode === 'vendeur') {
            return $this->sellerOwnsAllLignes($commande, (int) $user->getId());
        }

        return true;
    }

    /**
     * Libellés vendeurs (noms utilisateurs) pour la liste admin, sans montants.
     *
     * @param list<Commandes> $commandes
     *
     * @return array<int, string>
     */
    private function buildVendeurLabelsForCommandesAdmin(array $commandes): array
    {
        if ($commandes === []) {
            return [];
        }

        $byId = [];
        foreach ($commandes as $c) {
            $byId[$c->getId()] = $c;
        }

        $commandeIds = array_keys($byId);
        $grouped = $this->ligneCommandeRepository->findDistinctFournisseurIdsGroupedByCommande($commandeIds);

        foreach ($byId as $cid => $commande) {
            if (($grouped[$cid] ?? []) === [] && $commande->getIdFournisseur() !== null) {
                $grouped[$cid] = [(int) $commande->getIdFournisseur()];
            }
        }

        $allFids = [];
        foreach ($grouped as $fids) {
            foreach ($fids as $fid) {
                $allFids[$fid] = true;
            }
        }

        $nameByUserId = [];
        if ($allFids !== []) {
            $users = $this->userRepository->findBy(['id' => array_keys($allFids)]);
            foreach ($users as $u) {
                $uid = (int) $u->getId();
                $label = trim((string) $u->getNom());
                $nameByUserId[$uid] = $label !== '' ? $label : ('#'.$uid);
            }
        }

        $out = [];
        foreach (array_keys($byId) as $cid) {
            $fids = $grouped[$cid] ?? [];
            if ($fids === []) {
                $out[$cid] = '—';
                continue;
            }
            $labels = [];
            foreach ($fids as $fid) {
                $labels[] = $nameByUserId[$fid] ?? ('#'.$fid);
            }
            $out[$cid] = implode(', ', array_unique($labels));
        }

        return $out;
    }

    private function sellerOwnsAllLignes(Commandes $commande, int $sellerUserId): bool
    {
        $lignes = $this->ligneCommandeRepository->findBy(['idCommande' => $commande->getId()]);
        if ($lignes === []) {
            return false;
        }
        foreach ($lignes as $l) {
            if ($l->getIdFournisseur() !== $sellerUserId) {
                return false;
            }
        }

        return true;
    }

    private function resolveMesCommandesListMode(): string
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return 'admin';
        }
        if ($this->isMarcheAcheteur()) {
            return 'acheteur';
        }
        if ($this->isMesCommandesVendeurContext()) {
            return 'vendeur';
        }

        return 'acheteur';
    }

    private function wantsOrderEditAjaxResponse(Request $request): bool
    {
        $accept = (string) $request->headers->get('Accept');

        return $request->isXmlHttpRequest() || str_contains($accept, 'application/json');
    }

    /**
     * @return array{
     *     mes_commandes_mode: string,
     *     commande: Commandes,
     *     lignes: list<\App\Entity\Marketplace\LigneCommande>,
     *     montant_vendeur: float|null,
     *     has_autres_vendeurs: bool,
     *     statut_choices: array<string, string>,
     *     commande_deletable: bool
     * }
     */
    private function buildOrderDetailVars(int $id, User $user): array
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $commande = $this->findCommandeAdmin($id);
            $lignes = $this->ligneCommandeRepository->findBy(['idCommande' => $commande->getId()], ['id' => 'ASC']);

            return array_merge([
                'mes_commandes_mode' => 'admin',
                'commande' => $commande,
                'lignes' => $lignes,
                'montant_vendeur' => null,
                'has_autres_vendeurs' => false,
                'statut_choices' => self::STATUTS_VENDEUR_ACTION,
                'commande_deletable' => $this->commandeMayBeDeleted($commande, $user, 'admin'),
                'force_statut_edit' => true,
            ], $this->cancellationContextForCommande($commande, 'admin'));
        }

        if ($this->isMarcheAcheteur()) {
            $commande = $this->findOwnedCommandeAcheteur($id, $user);
            $lignes = $this->ligneCommandeRepository->findBy(['idCommande' => $commande->getId()], ['id' => 'ASC']);

            return array_merge([
                'mes_commandes_mode' => 'acheteur',
                'commande' => $commande,
                'lignes' => $lignes,
                'montant_vendeur' => null,
                'has_autres_vendeurs' => false,
                'statut_choices' => $this->acheteurStatutChoices($commande),
                'commande_deletable' => $this->commandeMayBeDeleted($commande, $user, 'acheteur'),
                'force_statut_edit' => false,
            ], $this->cancellationContextForCommande($commande, 'acheteur'));
        }

        if ($this->isMesCommandesVendeurContext()) {
            $sellerId = (int) $user->getId();
            $commande = $this->findAccessibleCommandeVendeur($id, $sellerId);
            $lignes = $this->ligneCommandeRepository->findByCommandeAndFournisseur($commande->getId(), $sellerId);
            $montantVendeur = $this->ligneCommandeRepository->sumMontantForFournisseurOnCommande($commande->getId(), $sellerId);
            $toutesLignes = $this->ligneCommandeRepository->findBy(['idCommande' => $commande->getId()], ['id' => 'ASC']);
            $hasAutres = \count($toutesLignes) > \count($lignes);

            return array_merge([
                'mes_commandes_mode' => 'vendeur',
                'commande' => $commande,
                'lignes' => $lignes,
                'montant_vendeur' => $montantVendeur,
                'has_autres_vendeurs' => $hasAutres,
                'statut_choices' => self::STATUTS_VENDEUR_ACTION,
                'commande_deletable' => $this->commandeMayBeDeleted($commande, $user, 'vendeur'),
                'force_statut_edit' => false,
            ], $this->cancellationContextForCommande($commande, 'vendeur'));
        }

        $commande = $this->findOwnedCommandeAcheteur($id, $user);
        $lignes = $this->ligneCommandeRepository->findBy(['idCommande' => $commande->getId()], ['id' => 'ASC']);

        return array_merge([
            'mes_commandes_mode' => 'acheteur',
            'commande' => $commande,
            'lignes' => $lignes,
            'montant_vendeur' => null,
            'has_autres_vendeurs' => false,
            'statut_choices' => $this->acheteurStatutChoices($commande),
            'commande_deletable' => $this->commandeMayBeDeleted($commande, $user, 'acheteur'),
            'force_statut_edit' => false,
        ], $this->cancellationContextForCommande($commande, 'acheteur'));
    }

    private function findCommandeAdmin(int $id): Commandes
    {
        $commande = $this->commandesRepository->find($id);
        if (!$commande instanceof Commandes) {
            throw $this->createNotFoundException();
        }

        return $commande;
    }

    /**
     * @param list<Commandes> $commandes
     *
     * @return array<int, array<string, string>>
     */
    private function adminStatutChoicesByCommandes(array $commandes): array
    {
        $all = self::STATUTS_VENDEUR_ACTION;
        $out = [];
        foreach ($commandes as $c) {
            $out[$c->getId()] = $all;
        }

        return $out;
    }
}
