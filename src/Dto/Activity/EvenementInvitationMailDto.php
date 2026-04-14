<?php

namespace App\Dto\Activity;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class EvenementInvitationMailDto
{
    private ?string $email = null;

    private ?UploadedFile $pdfFile = null;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPdfFile(): ?UploadedFile
    {
        return $this->pdfFile;
    }

    public function setPdfFile(?UploadedFile $pdfFile): self
    {
        $this->pdfFile = $pdfFile;

        return $this;
    }
}