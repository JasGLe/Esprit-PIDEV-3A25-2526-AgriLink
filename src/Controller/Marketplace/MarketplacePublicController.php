<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Produits;
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
    ) {
    }

    #[Route('', name: 'marketplace_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $q = trim($request->query->getString('q', ''));
        $cat = strtolower($request->query->getString('cat', 'all'));
        $region = trim($request->query->getString('region', ''));
        $sort = $request->query->getString('sort', 'price_asc');
        if (!\in_array($sort, ['price_asc', 'price_desc', 'recent'], true)) {
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
                return $this->renderResponse($request, $q, $cat, $region, $sort, []);
            }
        }

        $produits = $this->produitsRepository->findPublicMarketplaceCatalog(
            $q !== '' ? $q : null,
            $cat,
            $sellerIdsForRegion,
            $sort
        );

        return $this->renderResponse($request, $q, $cat, $region, $sort, $produits);
    }

    /**
     * @param Produits[] $produits
     */
    private function renderResponse(
        Request $request,
        string $q,
        string $cat,
        string $region,
        string $sort,
        array $produits,
        ?array $villesRegion = null,
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

        if ($villesRegion === null) {
            $sellerIdsForDropdown = $this->produitsRepository->findDistinctSellerIdsPublicCatalog();
            $villesRegion = $this->userRepository->findDistinctVillesByUserIds($sellerIdsForDropdown);
        }

        return $this->render('marketplace/public/index.html.twig', [
            'produits' => $produits,
            'vendeurs' => $vendeurs,
            'villes_region' => $villesRegion,
            'filter_q' => $q,
            'filter_cat' => $cat,
            'filter_region' => $region,
            'filter_sort' => $sort,
            'query_params' => $request->query->all(),
            'panier_count' => 0,
        ]);
    }
}
