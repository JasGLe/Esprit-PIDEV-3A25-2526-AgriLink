<?php

namespace App\Service;

use App\Entity\Marketplace\Commandes;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class DeliveryConfirmationService
{
    public function __construct(
        private readonly RouterInterface $router,
    ) {
    }

    /** Génère un jeton unique lorsque la commande est expédiée ou livrée (bon de livraison). */
    public function ensureDeliveryConfirmationToken(Commandes $order): void
    {
        if (!\in_array($order->getStatus(), ['EXPEDIEE', 'LIVREE'], true)) {
            return;
        }

        $existing = $order->getDeliveryConfirmationToken();
        if (\is_string($existing) && strlen($existing) >= 32) {
            return;
        }

        $order->setDeliveryConfirmationToken(bin2hex(random_bytes(32)));
    }

    public function getPublicConfirmationUrl(Commandes $order): ?string
    {
        $token = $order->getDeliveryConfirmationToken();
        if (!\is_string($token) || $token === '') {
            return null;
        }

        return $this->router->generate(
            'delivery_confirm',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    public function buildQrDataUri(string $targetUrl): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($targetUrl)
            ->size(220)
            ->margin(8)
            ->build();

        return $result->getDataUri();
    }
}
