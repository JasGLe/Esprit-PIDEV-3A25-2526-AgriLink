<?php

namespace App\Service;

class VoiceRecognitionService
{
    /**
     * Euclidean distance threshold for voice speaker verification.
     * Same speaker: distance typically 0.3–0.60. Different speaker: distance typically 0.7+.
     */
    private const VOICE_EUCLIDEAN_THRESHOLD = 0.7;

    /**
     * Store voice embedding for a user
     *
     * @param list<float|int> $embedding
     */
    public function storeVoiceEmbedding(array $embedding): string
    {
        return json_encode($embedding);
    }

    /**
     * Verify if a voice matches the stored embedding.
     * Returns true when the Euclidean distance is within the accepted threshold.
     *
     * @param list<float|int> $capturedEmbedding
     */
    public function verifyVoice(string $storedEmbeddingJson, array $capturedEmbedding): bool
    {
        try {
            $storedEmbedding = json_decode($storedEmbeddingJson, true);
            if (!is_array($storedEmbedding) || count($storedEmbedding) !== count($capturedEmbedding)) {
                return false;
            }

            /** @var list<float|int> $storedList */
            $storedList = array_values($storedEmbedding);
            $capturedList = $capturedEmbedding;

            $distance = $this->euclideanDistance($storedList, $capturedList);
            return $this->isWithinThreshold($distance);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Calculate Euclidean distance between two 256-float voice embeddings.
     * Lower distance = more similar voice profiles.
     *
     * @param list<float|int> $embedding1
     * @param list<float|int> $embedding2
     */
    public function euclideanDistance(array $embedding1, array $embedding2): float
    {
        $sumSquares = 0.0;
        for ($i = 0; $i < count($embedding1); $i++) {
            $diff = (float)$embedding1[$i] - (float)$embedding2[$i];
            $sumSquares += $diff * $diff;
        }

        return sqrt($sumSquares);
    }

    /**
     * Return true when the pre-computed distance is within the acceptance threshold.
     */
    public function isWithinThreshold(float $distance): bool
    {
        return $distance <= self::VOICE_EUCLIDEAN_THRESHOLD;
    }

    /**
     * Get the Euclidean distance threshold.
     */
    public function getThreshold(): float
    {
        return self::VOICE_EUCLIDEAN_THRESHOLD;
    }
}
