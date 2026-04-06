<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Produits;
use App\Entity\UserManagement\User;
use App\Marketplace\TunisiaRegionList;
use App\Repository\Marketplace\PanierRepository;
use App\Repository\Marketplace\ProduitsRepository;
use App\Repository\UserManagement\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/marketplace')]
class MarketplacePublicController extends AbstractController
{
    public function __construct(
        private readonly ProduitsRepository $produitsRepository,
        private readonly UserRepository $userRepository,
        private readonly PanierRepository $panierRepository,
    ) {
    }

    #[Route('', name: 'marketplace_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $cat = strtolower($request->query->getString('cat', 'all'));
        $region = trim($request->query->getString('region', ''));
        $q = trim($request->query->getString('q', ''));
        $sort = $request->query->getString('sort', 'price_asc');
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
            if ($sellerIdsForRegion === []) {
                return $this->renderResponse($request, $cat, $region, $sort, []);
            }
        }

        $produits = $this->produitsRepository->findPublicMarketplaceCatalog(
            $q !== '' ? $q : null,
            $cat,
            $sellerIdsForRegion,
            $sort
        );

        return $this->renderResponse($request, $cat, $region, $sort, $produits);
    }

    /**
     * @param Produits[] $produits
     */
    private function renderResponse(
        Request $request,
        string $cat,
        string $region,
        string $sort,
        array $produits,
    ): Response {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (Produits $p) => $p->getIdFournisseur(),
            $produits
        ))));

        $vendeurs = [];
        if ($ids !== []) {
            foreach ($this->userRepository->findBy(['id' => $ids]) as $user) {
                $vendeurs[(int) $user->getId()] = [
                    'name' => $user->getDisplayName(),
                    'ville' => $user->getVille(),
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

        return $this->render('marketplace/public/index.html.twig', [
            'produits' => $produits,
            'vendeurs' => $vendeurs,
            'tunisia_regions' => TunisiaRegionList::getGovernorates(),
            'filter_cat' => $cat,
            'filter_region' => $region,
            'filter_sort' => $sort,
            'filter_q' => trim($request->query->getString('q', '')),
            'query_params' => $queryParams,
            'panier_count' => $panierCount,
        ]);
    }
}
