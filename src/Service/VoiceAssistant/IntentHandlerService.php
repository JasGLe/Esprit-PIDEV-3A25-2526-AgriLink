<?php

namespace App\Service\VoiceAssistant;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class IntentHandlerService
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AuthorizationCheckerInterface $auth,
    ) {
    }

    /**
     * @param array<string, mixed> $entities
     *
     * @return array{
     *   message: string,
     *   action: array{type: string, url?: string}
     * }
     */
    public function handle(string $intent, string $text, array $entities = []): array
    {
        $intent = strtolower(trim($intent));

        return match ($intent) {
            'view_orders' => $this->navigate('mes_commandes_index', 'D’accord. Voici vos commandes.'),
            'go_dashboard' => $this->navigate('sales_stats_index', 'D’accord. J’ouvre le tableau de bord des ventes.'),
            'go_marketplace' => $this->navigate('marketplace_index', 'D’accord. Retour au marketplace.'),
            'go_boutique' => $this->navigate('boutique_index', 'D’accord. J’ouvre votre boutique.'),
            'add_product' => $this->handleAddProduct(),
            'delete_product' => $this->handleDeleteProduct($entities),
            default => [
                'message' => 'Commande non comprise. Essayez: "voir mes commandes", "aller au dashboard", "aller marketplace".',
                'action' => ['type' => 'none'],
            ],
        };
    }

    /**
     * @return array{message: string, action: array{type: string, url?: string}}
     */
    private function navigate(string $routeName, string $message): array
    {
        return [
            'message' => $message,
            'action' => [
                'type' => 'navigate',
                'url' => $this->urlGenerator->generate($routeName),
            ],
        ];
    }

    private function handleAddProduct(): array
    {
        // "Add product" depends on the seller workflow (culture/equipement).
        // We keep this safe and navigate to boutique home (user can choose what to sell).
        if (!$this->auth->isGranted('ROLE_AGRICULTEUR')) {
            return [
                'message' => 'Vous devez être agriculteur pour ajouter un produit.',
                'action' => ['type' => 'none'],
            ];
        }

        return $this->navigate('boutique_index', 'D’accord. Ouvrez une culture et cliquez sur "Mettre en vente".');
    }

    /**
     * We do NOT delete via voice for safety; we only guide the user.
     *
     * @param array<string, mixed> $entities
     */
    private function handleDeleteProduct(array $entities): array
    {
        $pid = isset($entities['productId']) ? (int) $entities['productId'] : 0;
        if (!$this->auth->isGranted('ROLE_AGRICULTEUR')) {
            return [
                'message' => 'Vous devez être agriculteur pour supprimer un produit.',
                'action' => ['type' => 'none'],
            ];
        }

        if ($pid > 0) {
            return $this->navigate('boutique_index', sprintf('J’ouvre votre boutique. Supprimez le produit #%d depuis la liste.', $pid));
        }

        return $this->navigate('boutique_index', 'J’ouvre votre boutique. Dites aussi le numéro du produit: "supprimer produit 12".');
    }
}

