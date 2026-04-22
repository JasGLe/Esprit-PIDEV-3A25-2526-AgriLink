<?php

namespace App\Controller\Marketplace;

use App\Service\LibreTranslateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/marketplace/i18n', name: 'marketplace_i18n_')]
class MarketplaceTranslationController extends AbstractController
{
    #[Route('/translate', name: 'translate', methods: ['POST'])]
    public function translate(Request $request, LibreTranslateService $libreTranslateService): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['translations' => []]);
        }

        $texts = $payload['texts'] ?? [];
        $target = strtolower(trim((string) ($payload['target'] ?? 'fr')));
        if (!\is_array($texts) || !\in_array($target, ['fr', 'en', 'ar'], true)) {
            return $this->json(['translations' => []]);
        }

        $texts = array_values(array_filter(array_map(static fn ($v) => (string) $v, $texts), static fn ($v) => trim($v) !== ''));
        if ($texts === [] || $target === 'fr') {
            return $this->json(['translations' => []]);
        }

        // Prevent oversized payloads from blocking the request.
        if (\count($texts) > 300) {
            $texts = \array_slice($texts, 0, 300);
        }

        $translations = $libreTranslateService->translateBatch($texts, $target, 'fr');

        return $this->json(['translations' => $translations]);
    }
}
