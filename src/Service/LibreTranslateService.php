<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LibreTranslateService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $libreTranslateBaseUrl,
        private readonly ?string $libreTranslateApiKey = null,
    ) {
    }

    /**
     * @param list<string> $texts
     *
     * @return array<string, string>
     */
    public function translateBatch(array $texts, string $target, string $source = 'fr'): array
    {
        $normalized = [];
        foreach ($texts as $text) {
            $value = trim((string) $text);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        $normalized = array_values(array_unique($normalized));
        if ($normalized === [] || $target === $source) {
            return [];
        }

        $batchMapped = $this->translateWithBatchPayload($normalized, $source, $target);
        if ($batchMapped !== []) {
            return $batchMapped;
        }

        // Fallback mode for stricter/public instances: request one text at a time.
        $mapped = [];
        foreach ($normalized as $text) {
            $mapped[$text] = $this->translateSingle($text, $source, $target) ?? $text;
        }

        return $mapped;
    }

    /**
     * @param list<string> $texts
     *
     * @return array<string, string>
     */
    private function translateWithBatchPayload(array $texts, string $source, string $target): array
    {
        $payload = $this->basePayload($source, $target);
        $payload['q'] = $texts;

        $data = $this->sendRequest($payload);
        if ($data === null) {
            return [];
        }

        $translatedValues = $this->extractTranslatedValues($data);
        if ($translatedValues === [] || \count($translatedValues) !== \count($texts)) {
            return [];
        }

        $mapped = [];
        foreach ($texts as $i => $original) {
            $mapped[$original] = $translatedValues[$i] ?? $original;
        }

        return $mapped;
    }

    private function translateSingle(string $text, string $source, string $target): ?string
    {
        $payload = $this->basePayload($source, $target);
        $payload['q'] = $text;

        $data = $this->sendRequest($payload);
        if ($data === null) {
            return null;
        }

        $values = $this->extractTranslatedValues($data);
        if ($values === []) {
            return null;
        }

        return $values[0] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(string $source, string $target): array
    {
        $payload = [
            'source' => $source,
            'target' => $target,
            'format' => 'text',
        ];
        if ($this->libreTranslateApiKey !== null && trim($this->libreTranslateApiKey) !== '') {
            $payload['api_key'] = $this->libreTranslateApiKey;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|list<mixed>|null
     */
    private function sendRequest(array $payload): array|null
    {
        try {
            $response = $this->httpClient->request('POST', rtrim($this->libreTranslateBaseUrl, '/').'/translate', [
                'json' => $payload,
                'timeout' => 20,
            ]);

            $data = $response->toArray(false);
            if (\is_array($data)) {
                return $data;
            }
        } catch (TransportExceptionInterface|\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @param array<string, mixed>|list<mixed> $data
     *
     * @return list<string>
     */
    private function extractTranslatedValues(array $data): array
    {
        $translatedValues = [];

        if (isset($data['translatedText']) && \is_string($data['translatedText'])) {
            $translatedValues[] = $data['translatedText'];
        } elseif (isset($data['translatedText']) && \is_array($data['translatedText'])) {
            foreach ($data['translatedText'] as $item) {
                if (\is_string($item)) {
                    $translatedValues[] = $item;
                }
            }
        } else {
            foreach ($data as $item) {
                if (\is_array($item) && isset($item['translatedText']) && \is_string($item['translatedText'])) {
                    $translatedValues[] = $item['translatedText'];
                }
            }
        }

        return $translatedValues;
    }
}
