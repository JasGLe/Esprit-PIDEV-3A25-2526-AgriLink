<?php

namespace App\Twig;

use App\Service\FileUploader;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FileUploaderExtension extends AbstractExtension
{
    public function __construct(
        private readonly FileUploader $fileUploader
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('upload_url', [$this, 'getUploadUrl']),
        ];
    }

    public function getUploadUrl(?string $relativePath): ?string
    {
        if (!$relativePath) {
            return null;
        }

        return $this->fileUploader->getFileUrl($relativePath);
    }
}
