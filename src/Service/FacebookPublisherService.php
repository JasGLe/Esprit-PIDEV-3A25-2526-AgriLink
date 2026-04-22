<?php

namespace App\Service;

use App\Entity\Marketplace\Produits;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FacebookPublisherService
{
    private const GRAPH_BASE = 'https://graph.facebook.com/v19.0';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly RouterInterface $router,
        private readonly string $appUrl,
        private readonly string $facebookPageId,
        private readonly string $facebookPageAccessToken,
    ) {
    }

    /**
     * Publie automatiquement le produit sur la page Facebook.
     * Ne doit jamais casser le flow de création produit (exceptions/log only).
     */
    public function publishNewProduct(Produits $produit): void
    {
        if (trim($this->facebookPageId) === '' || trim($this->facebookPageAccessToken) === '') {
            $this->logger->warning('[FacebookPublisher] skipped: missing page configuration');
            return;
        }

        $id = $produit->getId();
        if ($id === null || $id < 1) {
            return;
        }

        $message = $this->buildMessage($produit);
        $link = $this->buildMarketplaceLink($produit);
        $imageUrl = $this->buildImageUrlIfAny($produit);

        try {
            // Facebook cannot fetch localhost/127.0.0.1 assets; use feed post in that case.
            if ($imageUrl !== null && !$this->isLocalUrl($imageUrl)) {
                $photoPosted = $this->postPhoto($imageUrl, $message."\n\n".$link);
                if ($photoPosted) {
                    return;
                }
            }

            $this->postFeed($message, $link);
        } catch (\Throwable $e) {
            $this->logger->error('[FacebookPublisher] publish failed', [
                'product_id' => $id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function postFeed(string $message, string $link): bool
    {
        $url = sprintf('%s/%s/feed', self::GRAPH_BASE, $this->facebookPageId);
        $payload = [
            'message' => $message,
            'link' => $link,
            'access_token' => $this->facebookPageAccessToken,
        ];

        return $this->sendGraphPost($url, $payload);
    }

    private function postPhoto(string $imageUrl, string $caption): bool
    {
        $url = sprintf('%s/%s/photos', self::GRAPH_BASE, $this->facebookPageId);
        $payload = [
            // Use a publicly accessible image URL
            'url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $this->facebookPageAccessToken,
        ];

        return $this->sendGraphPost($url, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendGraphPost(string $url, array $payload): bool
    {
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'body' => $payload,
                'timeout' => 8.0,
            ]);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                $this->logger->warning('[FacebookPublisher] Graph API non-2xx', [
                    'status' => $status,
                    'url' => $url,
                    'body' => $response->getContent(false),
                ]);
                return false;
            }
            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('[FacebookPublisher] Graph API transport error', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function buildMessage(Produits $p): string
    {
        $name = trim((string) $p->getNom());
        $price = $p->getPrixUnitaire();
        $desc = trim((string) ($p->getDescription() ?? ''));
        if ($desc !== '' && mb_strlen($desc) > 220) {
            $desc = mb_substr($desc, 0, 217).'…';
        }

        $lines = [];
        $lines[] = 'Nouveau produit sur AgriLink';
        if ($name !== '') {
            $lines[] = '• '.$name;
        }
        $lines[] = '• Prix : '.number_format((float) $price, 3, ',', ' ').' TND';
        if ($desc !== '') {
            $lines[] = '';
            $lines[] = $desc;
        }

        return implode("\n", $lines);
    }

    private function buildMarketplaceLink(Produits $p): string
    {
        // Fallback: link to marketplace search by product id
        $id = (int) $p->getId();
        $path = $this->router->generate('marketplace_index', ['q' => '#'.$id], UrlGeneratorInterface::ABSOLUTE_PATH);

        return rtrim($this->appUrl, '/').$path;
    }

    private function buildImageUrlIfAny(Produits $p): ?string
    {
        $img = trim((string) ($p->getImage() ?? ''));
        if ($img === '') {
            return null;
        }

        // Boutique template uses:
        // - equipement product: /uploads/{image}
        // - culture product: /uploads/cultures/{image}
        $relative = $p->getEquipementId() !== null
            ? '/uploads/'.$img
            : '/uploads/cultures/'.$img;

        return rtrim($this->appUrl, '/').$relative;
    }

    private function isLocalUrl(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $host = strtolower($host);

        return $host === 'localhost' || $host === '127.0.0.1';
    }
}

