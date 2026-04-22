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
    private const POLLINATIONS_TEXT_BASE = 'https://text.pollinations.ai/';
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

    public function getTextGenerationUrl(Evenement $evenement): string
    {
        $prompt = $this->buildLinkedInPrompt($evenement);

        return self::POLLINATIONS_TEXT_BASE . rawurlencode($prompt);
    }

    public function buildLinkedInFallback(Evenement $evenement): string
    {
        $titre = trim((string) $evenement->getTitre());
        $date = $evenement->getDateEvenement()?->format('d/m/Y à H:i') ?? 'date à confirmer';
        $lieu = trim((string) $evenement->getLieu());
        $type = trim((string) $evenement->getTypeEvenement());
        $description = trim((string) $evenement->getDescription());
        $longDescription = $description !== ''
            ? mb_substr($description, 0, 420)
            : "Cet événement propose un programme riche: échanges avec des professionnels, retours d'expérience concrets, idées actionnables et opportunités de collaboration.";
        $hashtags = $this->buildEventHashtags($evenement);

        return sprintf(
            "Nous avons le plaisir de vous inviter à %s.\n" .
            "Rendez-vous le %s à %s pour une session immersive autour de %s.\n" .
            "%s\n" .
            "Inscrivez-vous et venez développer votre réseau avec nous.\n" .
            "%s",
            $titre !== '' ? $titre : 'à venir',
            $date,
            $lieu !== '' ? $lieu : 'lieu à confirmer',
            $type !== '' ? $type : 'l’innovation',
            $longDescription,
            $hashtags
        );
    }

    public function sanitizeLinkedInPostText(string $rawText, Evenement $evenement): string
    {
        $text = trim($rawText);
        if ($text === '') {
            return $this->buildLinkedInFallback($evenement);
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            $candidates = [
                $decoded['post_text'] ?? null,
                $decoded['content'] ?? null,
                $decoded['message'] ?? null,
                $decoded['output'] ?? null,
            ];

            foreach ($candidates as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '') {
                    $text = trim($candidate);
                    break;
                }
            }
        }

        // Defensive cleanup when model wraps text in assistant/debug payloads.
        $text = preg_replace('/\{\s*"role"\s*:\s*"assistant"[\s\S]*\}\s*$/i', '', $text) ?? $text;
        $text = preg_replace('/"reasoning_content"\s*:\s*"[\s\S]*?"/i', '', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text, " \t\n\r\0\x0B\"'");

        if ($text === '' || str_contains(mb_strtolower($text), 'reasoning_content')) {
            return $this->buildLinkedInFallback($evenement);
        }

        return mb_substr($text, 0, 900);
    }

    private function buildLinkedInPrompt(Evenement $evenement): string
    {
        $titre = trim((string) $evenement->getTitre());
        $date = $evenement->getDateEvenement()?->format('d/m/Y à H:i') ?? 'date à confirmer';
        $lieu = trim((string) $evenement->getLieu());
        $description = trim((string) $evenement->getDescription());

        return sprintf(
            "Rédige un post LinkedIn en français (entre 550 et 900 caractères) pour promouvoir cet événement.\n" .
            "Contraintes strictes: inclure le nom, la date, le lieu, le type et une description détaillée (2 à 4 phrases) avec un ton attractif.\n" .
            "Ajoute 8 à 12 hashtags pertinents basés sur les données de l'événement (titre, lieu, type, thème, secteur).\n" .
            "Ne mets aucun lien URL dans le texte.\n" .
            "Retourne uniquement le texte final du post, sans guillemets ni explications.\n" .
            "Nom: %s\nDate: %s\nLieu: %s\nDescription: %s",
            $titre !== '' ? $titre : 'Événement',
            $date,
            $lieu !== '' ? $lieu : 'Lieu à confirmer',
            $description !== '' ? $description : 'Événement autour de l’innovation.'
        );
    }

    private function buildEventHashtags(Evenement $evenement): string
    {
        $rawTags = [
            'event',
            'innovation',
            'networking',
            'agriculture',
            (string) $evenement->getTypeEvenement(),
            (string) $evenement->getLieu(),
            (string) $evenement->getTitre(),
        ];

        $tags = [];
        foreach ($rawTags as $rawTag) {
            $clean = $this->normalizeHashtagToken($rawTag);
            if ($clean !== '') {
                $tags[$clean] = '#' . $clean;
            }
        }

        return implode(' ', array_slice(array_values($tags), 0, 12));
    }

    private function normalizeHashtagToken(string $value): string
    {
        $value = mb_strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $value = str_replace(
            ['à', 'â', 'ä', 'á', 'ã', 'å', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'ö', 'õ', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ'],
            ['a', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y'],
            $value
        );

        $value = preg_replace('/[^a-z0-9]+/i', '', $value) ?? '';

        return mb_substr($value, 0, 28);
    }
}
