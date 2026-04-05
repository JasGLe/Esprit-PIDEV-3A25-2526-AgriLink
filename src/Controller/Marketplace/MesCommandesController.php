<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Commandes;
use App\Entity\UserManagement\User;
use App\Repository\Marketplace\CommandesRepository;
use App\Repository\Marketplace\LigneCommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    /** Choix proposés au vendeur : uniquement la suite logistique (sans les 2 statuts d’attente paiement initial). */
    private const STATUTS_VENDEUR_ACTION = [
        'EN_PREPARATION' => 'En préparation',
        'EXPEDIEE' => 'Expédiée',
        'LIVREE' => 'Livrée',
        'ANNULEE' => 'Annulée',
    ];

    public function __construct(
        private readonly CommandesRepository $commandesRepository,
        private readonly LigneCommandeRepository $ligneCommandeRepository,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isMarcheAcheteur()) {
            $commandes = $this->commandesRepository->findForMarketplaceClient($user);
            $counts = $this->countBuckets($commandes);

            return $this->render('marketplace/mes_commandes/index.html.twig', [
                'mes_commandes_mode' => 'acheteur',
                'commandes' => $commandes,
                'montants_vendeur' => [],
                'count_total' => $counts['total'],
                'count_en_cours' => $counts['en_cours'],
                'count_livrees' => $counts['livrees'],
                'count_annulations_attente' => 0,
                'statut_choices_seller' => null,
                'statut_choices_by_commande' => $this->acheteurStatutChoicesByCommandes($commandes),
            ]);
        }

        if ($this->isGranted('ROLE_AGRICULTEUR')) {
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

            return $this->render('marketplace/mes_commandes/index.html.twig', [
                'mes_commandes_mode' => 'vendeur',
                'commandes' => $commandes,
                'montants_vendeur' => $montants,
                'count_total' => $counts['total'],
                'count_en_cours' => $counts['en_cours'],
                'count_livrees' => $counts['livrees'],
                'count_annulations_attente' => $this->countAnnulationsEnAttente($commandes),
                'statut_choices_seller' => self::STATUTS_VENDEUR_ACTION,
                'statut_choices_by_commande' => [],
            ]);
        }

        $commandes = $this->commandesRepository->findForMarketplaceClient($user);
        $counts = $this->countBuckets($commandes);

        return $this->render('marketplace/mes_commandes/index.html.twig', [
            'mes_commandes_mode' => 'acheteur',
            'commandes' => $commandes,
            'montants_vendeur' => [],
            'count_total' => $counts['total'],
            'count_en_cours' => $counts['en_cours'],
            'count_livrees' => $counts['livrees'],
            'count_annulations_attente' => 0,
            'statut_choices_seller' => null,
            'statut_choices_by_commande' => $this->acheteurStatutChoicesByCommandes($commandes),
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isMarcheAcheteur()) {
            $commande = $this->findOwnedCommandeAcheteur($id, $user);
            $lignes = $this->ligneCommandeRepository->findBy(['idCommande' => $commande->getId()], ['id' => 'ASC']);

            return $this->render('marketplace/mes_commandes/show.html.twig', [
                'mes_commandes_mode' => 'acheteur',
                'commande' => $commande,
                'lignes' => $lignes,
                'montant_vendeur' => null,
                'has_autres_vendeurs' => false,
                'statut_choices' => $this->acheteurStatutChoices($commande),
            ]);
        }

        if ($this->isGranted('ROLE_AGRICULTEUR')) {
            $sellerId = (int) $user->getId();
            $commande = $this->findAccessibleCommandeVendeur($id, $sellerId);
            $lignes = $this->ligneCommandeRepository->findByCommandeAndFournisseur($commande->getId(), $sellerId);
            $montantVendeur = $this->ligneCommandeRepository->sumMontantForFournisseurOnCommande($commande->getId(), $sellerId);
            $toutesLignes = $this->ligneCommandeRepository->findBy(['idCommande' => $commande->getId()], ['id' => 'ASC']);
            $hasAutres = \count($toutesLignes) > \count($lignes);

            return $this->render('marketplace/mes_commandes/show.html.twig', [
                'mes_commandes_mode' => 'vendeur',
                'commande' => $commande,
                'lignes' => $lignes,
                'montant_vendeur' => $montantVendeur,
                'has_autres_vendeurs' => $hasAutres,
                'statut_choices' => self::STATUTS_VENDEUR_ACTION,
            ]);
        }

        $commande = $this->findOwnedCommandeAcheteur($id, $user);
        $lignes = $this->ligneCommandeRepository->findBy(['idCommande' => $commande->getId()], ['id' => 'ASC']);

        return $this->render('marketplace/mes_commandes/show.html.twig', [
            'mes_commandes_mode' => 'acheteur',
            'commande' => $commande,
            'lignes' => $lignes,
            'montant_vendeur' => null,
            'has_autres_vendeurs' => false,
            'statut_choices' => $this->acheteurStatutChoices($commande),
        ]);
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

        if ($this->isMarcheAcheteur()) {
            $commande = $this->findOwnedCommandeAcheteur($id, $user);
            $allowed = array_keys($this->acheteurStatutChoices($commande));
        } elseif ($this->isGranted('ROLE_AGRICULTEUR')) {
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
        $this->commandesRepository->save($commande, true);
        $this->addFlash('success', 'Statut de la commande mis à jour.');

        return $this->redirectAfterMesCommandesStatut($request, $id);
    }

    #[Route('/{id}/pdf', name: 'pdf', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function pdf(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isMarcheAcheteur()) {
            $this->findOwnedCommandeAcheteur($id, $user);
        } elseif ($this->isGranted('ROLE_AGRICULTEUR')) {
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

    /**
     * @param list<Commandes> $commandes
     */
    private function countAnnulationsEnAttente(array $commandes): int
    {
        $n = 0;
        foreach ($commandes as $c) {
            $s = strtolower($c->getStatus());
            if (str_contains($s, 'annulation') && str_contains($s, 'attente')) {
                ++$n;
            }
        }

        return $n;
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
     * @return array<string, string>
     */
    private function acheteurStatutChoices(Commandes $commande): array
    {
        $s = strtolower($commande->getStatus());
        if (str_contains($s, 'annul')) {
            return [];
        }
        if (str_contains($s, 'livree') || str_contains($s, 'exped') || str_contains($s, 'livrée') || str_contains($s, 'livré')) {
            return [];
        }

        $cur = $commande->getStatus();
        $label = self::STATUTS_COMMANDE_MARCHE[$cur] ?? ucfirst(strtolower(str_replace('_', ' ', $cur)));

        return [
            $cur => 'Conserver : '.$label,
            'ANNULEE' => 'Annuler la commande',
        ];
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
}
