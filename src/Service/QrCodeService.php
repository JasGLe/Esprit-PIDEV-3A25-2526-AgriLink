<?php

namespace App\Service;

class QrCodeService
{
    private const BASE_DEEP_LINK_URL = 'https://stately-rugelach-afdc78.netlify.app/event.html';

    /**
     * Generate QR code URL for an event deep link with UTF-8 encoded parameters.
     * @param array<string, mixed> $params Array containing: titre, date, heure, lieu, type, desc
     */
    public function generateQrCodeForEvent(array $params): string
    {
        $deepLinkUrl = $this->buildDeepLink([
            'titre' => (string) ($params['titre'] ?? ''),
            'date' => (string) ($params['date'] ?? ''),
            'heure' => (string) ($params['heure'] ?? ''),
            'lieu' => (string) ($params['lieu'] ?? ''),
            'type' => (string) ($params['type'] ?? ''),
            'desc' => (string) ($params['desc'] ?? ''),
        ]);

        return $this->generateQrCodeFromUrl($deepLinkUrl);
    }

    /**
     * Generate QR code URL for an activity deep link with UTF-8 encoded parameters.
     * @param array<string, mixed> $params Array containing: titre, type_act, debut, fin, statut, cout
     */
    public function generateQrCodeForActivity(array $params): string
    {
        $deepLinkUrl = $this->buildDeepLink([
            'titre' => (string) ($params['titre'] ?? ''),
            'type_act' => (string) ($params['type_act'] ?? ''),
            'debut' => (string) ($params['debut'] ?? ''),
            'fin' => (string) ($params['fin'] ?? ''),
            'statut' => (string) ($params['statut'] ?? ''),
            'cout' => (string) ($params['cout'] ?? ''),
        ]);

        return $this->generateQrCodeFromUrl($deepLinkUrl);
    }

    /**
     * Build the final deep-link URL with UTF-8 URL-encoded parameters.
     *
     * @param array<string, mixed> $params
     */
    public function buildDeepLink(array $params): string
    {
        $encoded = [];

        foreach ($params as $key => $value) {
            $encoded[] = $key . '=' . urlencode((string) $value);
        }

        return self::BASE_DEEP_LINK_URL . '?' . implode('&', $encoded);
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
     *
     * @param array<string, mixed>|string $data
     */
    public function generateQrCodeFromData(array|string $data): string
    {
        // Convert data to JSON if it's an array
        if (is_array($data)) {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            $content = $json === false ? '' : $json;
        } else {
            $content = $data;
        }

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
            return $this->generateQrCodeForEvent([
                'titre' => $title,
                'date' => '',
                'heure' => '',
                'lieu' => '',
                'type' => '',
                'desc' => '',
            ]);
        } elseif ($type === 'activity') {
            return $this->generateQrCodeForActivity([
                'titre' => $title,
                'type_act' => '',
                'debut' => '',
                'fin' => '',
                'statut' => '',
                'cout' => '',
            ]);
        }

        throw new \InvalidArgumentException("Invalid QR type: {$type}");
    }
}
