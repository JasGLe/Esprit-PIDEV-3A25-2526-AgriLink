<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class QrCodeService
{
    public function __construct(
        #[Autowire('%env(APP_URL)%')]
        private readonly string $appUrl,
    ) {
    }

    /**
     * Generate QR code URL for an event pointing to agrilink-gr/event.html with all parameters
     * @param array $params Array containing: titre, date (dd/mm/yyyy), heure, lieu, type, desc
     */
    public function generateQrCodeForEvent(array $params): string
    {
        // Build query string for event.html viewer
        $queryParams = [
            'titre' => $params['titre'] ?? '',
            'date' => $params['date'] ?? '',
            'heure' => $params['heure'] ?? '',
            'lieu' => $params['lieu'] ?? '',
            'type' => $params['type'] ?? '',
        ];
        
        if (!empty($params['desc'])) {
            $queryParams['desc'] = $params['desc'];
        }

        $eventHtmlUrl = $this->appUrl . '/agrilink-gr/event.html?' . http_build_query($queryParams);
        return $this->generateQrCodeFromUrl($eventHtmlUrl);
    }

    /**
     * Generate QR code URL for an activity pointing to agrilink-gr/event.html with all parameters
     * @param array $params Array containing: titre, type_act, debut (dd/mm/yyyy), fin (dd/mm/yyyy), statut, cout
     */
    public function generateQrCodeForActivity(array $params): string
    {
        // Build query string for event.html viewer
        $queryParams = [
            'titre' => $params['titre'] ?? '',
            'type_act' => $params['type_act'] ?? '',
            'debut' => $params['debut'] ?? '',
            'fin' => $params['fin'] ?? '',
            'statut' => $params['statut'] ?? '',
        ];
        
        if (!empty($params['cout'])) {
            $queryParams['cout'] = $params['cout'];
        }

        $eventHtmlUrl = $this->appUrl . '/agrilink-gr/event.html?' . http_build_query($queryParams);
        return $this->generateQrCodeFromUrl($eventHtmlUrl);
    }

    /**
     * Generate QR code from a URL
     * Returns the QR code image URL from qr-server.com
     */
    public function generateQrCodeFromUrl(string $url): string
    {
        // Use qr-server.com API to generate QR code
        // This is a free, reliable service that returns PNG
        $encodedContent = urlencode($url);
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$encodedContent}";

        return $qrUrl;
    }

    /**
     * Generate QR code from arbitrary data
     * Returns a data URL for use in <img> tags
     */
    public function generateQrCodeFromData(array|string $data): string
    {
        // Convert data to JSON if it's an array
        $content = is_array($data) ? json_encode($data) : $data;

        // Use qr-server.com API to generate QR code
        // This is a free, reliable service that returns PNG
        $encodedContent = urlencode($content);
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$encodedContent}";

        return $qrUrl;
    }

    /**
     * Generate QR code URL for direct image display
     */
    public function getQrCodeImageUrl(int $entityId, string $type, string $title): string
    {
        if ($type === 'event') {
            return $this->generateQrCodeForEvent($entityId, $title);
        } elseif ($type === 'activity') {
            return $this->generateQrCodeForActivity($entityId, $title);
        }

        throw new \InvalidArgumentException("Invalid QR type: {$type}");
    }
}
