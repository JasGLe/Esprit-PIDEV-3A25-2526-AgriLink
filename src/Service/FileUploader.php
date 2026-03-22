<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploader
{
    private string $uploadsBaseDir;
    private string $uploadsBaseUrl;
    private SluggerInterface $slugger;

    private const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        string $uploadsBaseDir,
        string $uploadsBaseUrl,
        SluggerInterface $slugger
    ) {
        $this->uploadsBaseDir = $uploadsBaseDir;
        $this->uploadsBaseUrl = $uploadsBaseUrl;
        $this->slugger = $slugger;
    }

    /**
     * Upload un fichier dans le sous-dossier spécifié
     *
     * @param UploadedFile $file Le fichier uploadé
     * @param string $subDirectory Sous-dossier (ex: 'profiles')
     * @param string|null $oldFilePath Ancien fichier à supprimer (optionnel)
     * @return string Le chemin relatif du fichier (ex: 'profiles/nom-fichier.jpg')
     * @throws FileException
     */
    public function upload(UploadedFile $file, string $subDirectory, ?string $oldFilePath = null): string
    {
        // Validation taille
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new FileException('Le fichier est trop volumineux (max 2 Mo).');
        }

        // Validation type MIME
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new FileException('Format de fichier non autorisé. Utilisez JPG, PNG ou WEBP.');
        }

        // Créer le dossier de destination s'il n'existe pas
        $targetDirectory = $this->uploadsBaseDir . '/' . $subDirectory;
        if (!is_dir($targetDirectory)) {
            if (!mkdir($targetDirectory, 0755, true)) {
                throw new FileException('Impossible de créer le dossier de destination.');
            }
        }

        // Générer un nom unique
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $extension = $file->guessExtension();
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

        // Déplacer le fichier
        try {
            $file->move($targetDirectory, $newFilename);
        } catch (FileException $e) {
            throw new FileException('Erreur lors de l\'upload du fichier: ' . $e->getMessage());
        }

        // Supprimer l'ancien fichier si fourni
        if ($oldFilePath) {
            $this->deleteFile($oldFilePath);
        }

        // Retourner le chemin relatif
        return $subDirectory . '/' . $newFilename;
    }

    /**
     * Supprime un fichier uploadé
     *
     * @param string $relativePath Chemin relatif (ex: 'profiles/nom-fichier.jpg')
     */
    public function deleteFile(string $relativePath): void
    {
        $fullPath = $this->uploadsBaseDir . '/' . $relativePath;
        if (file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * Génère l'URL complète pour accéder à un fichier
     *
     * @param string $relativePath Chemin relatif (ex: 'profiles/nom-fichier.jpg')
     * @return string L'URL complète
     */
    public function getFileUrl(string $relativePath): string
    {
        return $this->uploadsBaseUrl . '/' . $relativePath;
    }

    /**
     * Vérifie si le dossier uploads est accessible
     */
    public function isUploadsDirectoryWritable(): bool
    {
        return is_dir($this->uploadsBaseDir) && is_writable($this->uploadsBaseDir);
    }
}
