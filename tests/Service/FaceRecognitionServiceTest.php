<?php

namespace App\Tests\Service;

use App\Service\FaceRecognitionService;
use PHPUnit\Framework\TestCase;

final class FaceRecognitionServiceTest extends TestCase
{
    private FaceRecognitionService $service;

    protected function setUp(): void
    {
        $this->service = new FaceRecognitionService();
    }

    public function testEuclideanDistanceWithSameVectorsIsZero(): void
    {
        $a = [0.1, 0.2, 0.3];
        $b = [0.1, 0.2, 0.3];

        $this->assertSame(0.0, $this->service->euclideanDistance($a, $b));
    }

    public function testEuclideanDistanceWithDifferentVectors(): void
    {
        $a = [0.0, 0.0];
        $b = [3.0, 4.0];

        $this->assertSame(5.0, $this->service->euclideanDistance($a, $b));
    }

    public function testIsWithinThreshold(): void
    {
        $this->assertTrue($this->service->isWithinThreshold(0.6));
        $this->assertTrue($this->service->isWithinThreshold(0.59));
        $this->assertFalse($this->service->isWithinThreshold(0.600001));
    }

    public function testVerifyFaceReturnsFalseOnInvalidJson(): void
    {
        $this->assertFalse($this->service->verifyFace('{invalid', [0.1, 0.2]));
    }

    public function testVerifyFaceReturnsFalseOnMismatchedLengths(): void
    {
        $stored = json_encode([0.1, 0.2, 0.3], JSON_THROW_ON_ERROR);

        $this->assertFalse($this->service->verifyFace($stored, [0.1, 0.2]));
    }

    public function testVerifyFaceReturnsTrueWhenDistanceWithinThreshold(): void
    {
        $stored = json_encode([0.0, 0.0], JSON_THROW_ON_ERROR);

        $this->assertTrue($this->service->verifyFace($stored, [0.3, 0.4])); // distance 0.5
    }

    public function testVerifyFaceReturnsFalseWhenDistanceOutsideThreshold(): void
    {
        $stored = json_encode([0.0, 0.0], JSON_THROW_ON_ERROR);

        $this->assertFalse($this->service->verifyFace($stored, [1.0, 0.0])); // distance 1.0
    }
}

