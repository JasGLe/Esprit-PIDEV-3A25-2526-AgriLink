<?php

namespace App\Service;

use App\Entity\Activity\Evenement;

/**
 * Generates event poster images using Pollinations.ai.
 *
 * Pollinations.ai is completely free with no API key required.
 * It returns an image URL that can be embedded directly in the browser.
 * Generation time varies (often tens of seconds; Pollinations may cache similar requests).
 */
class EventPosterGeneratorService
{
    private const POLLINATIONS_BASE = 'https://image.pollinations.ai/prompt/';
    private const IMAGE_WIDTH = 768;
    private const IMAGE_HEIGHT = 512;

    /**
     * Builds a direct image URL from Pollinations.ai based on event data.
     * No HTTP call needed from the backend – the browser loads the image directly.
     *
     * @return array{url: string, prompt: string}
     */
    public function buildPosterUrl(Evenement $evenement): array
    {
        $prompt = $this->buildPrompt($evenement);
        $encodedPrompt = rawurlencode($prompt);

        $url = sprintf(
            '%s%s?width=%d&height=%d&seed=%d&nologo=true&enhance=true',
            self::POLLINATIONS_BASE,
            $encodedPrompt,
            self::IMAGE_WIDTH,
            self::IMAGE_HEIGHT,
            abs(crc32((string) $evenement->getId()) + time()) % 99999
        );

        return [
            'url' => $url,
            'prompt' => $prompt,
        ];
    }

    /**
     * Limits server-side fetch to Pollinations poster URLs (SSRF guard).
     */
    public function isAllowedRemotePosterUrl(string $url): bool
    {
        if (!filter_var($url, \FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        if (strtolower((string) ($parts['host'] ?? '')) !== 'image.pollinations.ai') {
            return false;
        }

        $path = (string) ($parts['path'] ?? '');

        return str_starts_with($path, '/prompt/');
    }

    /**
     * Prefer magic-byte sniffing so we send a correct image/* type even if the upstream
     * server mislabels the body (avoids browser CORB issues on <img> / blob usage).
     */
    public function guessImageContentType(string $binary, ?string $headerContentType): string
    {
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }

        if (str_starts_with($binary, 'GIF87a') || str_starts_with($binary, 'GIF89a')) {
            return 'image/gif';
        }

        if (\strlen($binary) >= 12 && str_starts_with($binary, 'RIFF') && substr($binary, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        if ($headerContentType !== null) {
            $main = strtolower(trim(explode(';', $headerContentType, 2)[0]));
            if (preg_match('#^image/[\w.+-]+$#', $main) === 1) {
                return $main;
            }
        }

        return '';
    }

    private function buildPrompt(Evenement $evenement): string
    {
        $titre = $evenement->getTitre();
        $date = $evenement->getDateEvenement()?->format('d/m/Y') ?? '';
        $lieu = $evenement->getLieu();
        $type = strtoupper((string) $evenement->getTypeEvenement());

        $typeFr = match ($type) {
            'OFFICIEL' => 'Événement officiel',
            'PERSONNEL' => 'Événement personnel',
            default => 'Événement agricole',
        };

        return sprintf(
            'Affiche événement agricole professionnelle, palette vert et bleu, fond clair, hiérarchie visuelle claire, '
            . 'qualité print. Le visuel DOIT inclure du texte en français, très lisible, gros caractères sans serif, fort contraste : '
            . 'titre principal exactement « %s » ; ligne « Date : %s » ; ligne « Lieu : %s » ; ligne « Type : %s ». '
            . 'Disposition type affiche A4 horizontale, texte net, pas de texte illisible ni bruité.',
            $titre,
            $date,
            $lieu,
            $typeFr
        );
    }
}
