<?php

namespace App\Service;

class FaceRecognitionService
{
    /**
     * Industry-standard Euclidean distance threshold for face-api.js FaceRecognitionNet.
     * Same person: distance typically 0.3–0.55. Different person: distance typically 0.6+.
     */
    private const EUCLIDEAN_THRESHOLD = 0.6;

    /**
     * Store face descriptor for a user
     *
     * @param list<float|int> $descriptor
     */
    public function storeFaceDescriptor(array $descriptor): string
    {
        return json_encode($descriptor);
    }

    /**
     * Verify if a face matches the stored descriptor.
     * Returns true when the Euclidean distance is within the accepted threshold.
     *
     * @param array<int, float|int> $capturedDescriptor
     */
    public function verifyFace(string $storedDescriptorJson, array $capturedDescriptor): bool
    {
        try {
            $storedDescriptor = json_decode($storedDescriptorJson, true);
            if (!is_array($storedDescriptor) || count($storedDescriptor) !== count($capturedDescriptor)) {
                return false;
            }

            /** @var list<float|int> $storedList */
            $storedList = array_values($storedDescriptor);
            /** @var list<float|int> $capturedList */
            $capturedList = array_values($capturedDescriptor);

            $distance = $this->euclideanDistance($storedList, $capturedList);
            return $this->isWithinThreshold($distance);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Calculate Euclidean distance between two 128-float face descriptors.
     * Lower distance = more similar faces.
     *
     * @param list<float|int> $descriptor1
     * @param list<float|int> $descriptor2
     */
    public function euclideanDistance(array $descriptor1, array $descriptor2): float
    {
        $sumSquares = 0.0;
        for ($i = 0; $i < count($descriptor1); $i++) {
            $diff = (float)$descriptor1[$i] - (float)$descriptor2[$i];
            $sumSquares += $diff * $diff;
        }

        return sqrt($sumSquares);
    }

    /**
     * Return true when the pre-computed distance is within the acceptance threshold.
     */
    public function isWithinThreshold(float $distance): bool
    {
        return $distance <= self::EUCLIDEAN_THRESHOLD;
    }

    /**
     * Get the Euclidean distance threshold.
     */
    public function getThreshold(): float
    {
        return self::EUCLIDEAN_THRESHOLD;
    }
}
