<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AvatarService
{
    private HttpClientInterface $httpClient;
    private string $appUrl;
    private string $uploadsBaseUrl;

    private const IMAGE_ENDPOINT = 'https://image.pollinations.ai/prompt/';
    private const IMG2IMG_MODEL = 'flux-redux';
    private const TEXT2IMG_MODEL = 'flux-anime';
    private const AVATAR_SIZE = 512;

    public function __construct(
        string $appUrl = 'http://localhost',
        string $uploadsBaseUrl = '/agrilink/uploads'
    ) {
        $this->appUrl = rtrim($appUrl, '/');
        $this->uploadsBaseUrl = rtrim($uploadsBaseUrl, '/');
        $this->httpClient = HttpClient::create();
    }

    /**
     * Generate an avatar from a profile image using Pollinations.ai (free, no API key required).
     *
     * @param string $imagePath        Full filesystem path to the profile photo
     * @param string $photoRelativePath Relative path stored in User entity (e.g. "profiles/foo.jpg")
     * @return string Binary image data (JPEG/PNG)
     * @throws \Exception
     */
    public function generateAvatar(string $imagePath, string $photoRelativePath = ''): string
    {
        if (!file_exists($imagePath) || !is_readable($imagePath)) {
            throw new \Exception('Profile image not found or not readable.');
        }

        $prompt = 'anime-style avatar portrait that faithfully matches the exact facial features, skin tone, hair color, hair style, and face shape of the person in the reference photo. Preserve the identity and likeness of the subject. Vibrant colors, friendly expression, high quality digital art, headshot only, clean simple background. The avatar MUST look like the same person.';

        // Try img2img when the app is publicly reachable (i.e. not localhost)
        if ($photoRelativePath && !$this->isLocalhost()) {
            $imageUrl = $this->appUrl . $this->uploadsBaseUrl . '/' . ltrim($photoRelativePath, '/');
            try {
                return $this->callApi($prompt, self::IMG2IMG_MODEL, $imageUrl);
            } catch (\Exception) {
                // Fall through to text-to-image
            }
        }

        // Text-to-image fallback (always works, no public URL needed)
        return $this->callApi($prompt, self::TEXT2IMG_MODEL);
    }

    private function callApi(string $prompt, string $model, ?string $imageUrl = null): string
    {
        $encodedPrompt = rawurlencode($prompt);
        $url = self::IMAGE_ENDPOINT . $encodedPrompt;

        $query = [
            'model'   => $model,
            'width'   => self::AVATAR_SIZE,
            'height'  => self::AVATAR_SIZE,
            'nologo'  => 'true',
            'seed'    => random_int(1, 999999),
        ];

        if ($imageUrl !== null) {
            $query['image_url'] = $imageUrl;
        }

        $lastException = null;
        $maxAttempts   = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'query'   => $query,
                    'timeout' => 150,
                    'headers' => [
                        'User-Agent' => 'AgriLink/1.0',
                        'Accept'     => 'image/*',
                    ],
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode >= 500) {
                    throw new \Exception('Pollinations.ai est temporairement indisponible (HTTP ' . $statusCode . ').');
                }

                if ($statusCode !== 200) {
                    throw new \Exception('Échec de la génération de l\'avatar (HTTP ' . $statusCode . ').');
                }

                $content = $response->getContent();

                if (empty($content) || strlen($content) < 500) {
                    throw new \Exception('Aucune donnée d\'image valide reçue de Pollinations.ai.');
                }

                return $content;

            } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
                $lastException = new \Exception(
                    'Le service de génération est temporairement indisponible. Veuillez réessayer dans quelques instants.',
                    0,
                    $e
                );

                if ($attempt < $maxAttempts) {
                    sleep(2);
                    $query['seed'] = random_int(1, 999999);
                    continue;
                }

            } catch (\Exception $e) {
                $lastException = $e;

                if ($attempt < $maxAttempts && str_contains($e->getMessage(), 'indisponible')) {
                    sleep(2);
                    continue;
                }

                throw $e;
            }
        }

        throw $lastException;
    }

    private function isLocalhost(): bool
    {
        $host = parse_url($this->appUrl, PHP_URL_HOST);
        if (!is_string($host)) {
            $host = '';
        }
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.local');
    }

    /**
     * Always true — Pollinations.ai is free and requires no API key.
     */
    public function isConfigured(): bool
    {
        return true;
    }

    public function getConfigurationStatus(): string
    {
        return 'Avatar service ready — uses Pollinations.ai (free, no API key required).';
    }
}
