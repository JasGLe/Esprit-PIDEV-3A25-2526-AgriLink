<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * FileUploader
 * ─────────────────────────────────────────────────────────────────────────────
 * Service générique d'upload de fichiers image pour AgriLink.
 *
 * Utilisé principalement par EquipementController pour la photo de l'équipement.
 * Peut servir pour d'autres modules nécessitant un upload d'image.
 *
 * Fonctionnement :
 *  1. Valide la taille du fichier (max 2 Mo).
 *  2. Valide le type MIME (image/jpeg, image/jpg, image/png, image/webp).
 *  3. Crée le dossier de destination s'il n'existe pas.
 *  4. Génère un nom de fichier unique (slug-uniqid.extension) pour éviter
 *     les collisions et les noms de fichiers malveillants dans les URLs.
 *  5. Déplace le fichier dans le dossier de destination.
 *  6. Supprime l'ancien fichier si un chemin est fourni (remplacement d'image).
 *  7. Retourne le chemin relatif (ex: 'equipements/tracteur-65f3a9b2.jpg').
 *
 * Configuration (via services.yaml et .env.local) :
 *  - UPLOADS_BASE_DIR : chemin absolu du dossier uploads
 *    (ex: C:/Users/rayen/Esprit-PIDEV-3A25-2526-AgriLink/public/uploads pour symfony serve)
 *  - UPLOADS_BASE_URL : URL de base accessible par le navigateur
 *    (ex: /uploads pour symfony serve)
 *
 * Le chemin relatif retourné est stocké en base (Equipement::imageUrl).
 * Dans les templates Twig, l'URL complète est construite avec :
 *   {{ uploads_base_url }}/{{ equipement.imageUrl|split('/')|last }}
 * ─────────────────────────────────────────────────────────────────────────────
 */
class FileUploader
{
    /** Taille maximale autorisée en octets (2 Mo). */
    private const MAX_FILE_SIZE = 2 * 1024 * 1024;

    /** Types MIME image acceptés (JPEG, PNG, WebP). */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
    ];

    /**
     * @param string           $uploadsBaseDir  Chemin absolu du dossier uploads (depuis UPLOADS_BASE_DIR)
     * @param string           $uploadsBaseUrl  URL de base pour construire les URLs publiques (UPLOADS_BASE_URL)
     * @param SluggerInterface $slugger         Slugger Symfony pour nettoyer les noms de fichiers
     */
    public function __construct(
        private string $uploadsBaseDir,
        private string $uploadsBaseUrl,
        private SluggerInterface $slugger
    ) {}

    /**
     * Upload un fichier image dans un sous-dossier du répertoire uploads.
     *
     * Si $oldFilePath est fourni, l'ancien fichier est supprimé après le
     * déplacement du nouveau (permet de remplacer la photo d'un équipement
     * sans laisser des fichiers orphelins).
     *
     * @param UploadedFile $file          Fichier uploadé par le formulaire Symfony
     * @param string       $subDirectory  Sous-dossier de destination (ex: 'equipements', 'profiles')
     * @param string|null  $oldFilePath   Chemin relatif de l'ancien fichier à supprimer (optionnel)
     *
     * @return string  Chemin relatif du fichier sauvegardé (ex: 'equipements/tracteur-65f3a9b2.jpg')
     *
     * @throws FileException  Si la taille dépasse 2 Mo, si le type MIME n'est pas autorisé,
     *                        si le dossier ne peut pas être créé, ou si le déplacement échoue
     */
    public function upload(UploadedFile $file, string $subDirectory, ?string $oldFilePath = null): string
    {
        // ── Validation de la taille (max 2 Mo) ───────────────────────────────
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new FileException('Le fichier est trop volumineux (max 2 Mo).');
        }

        // ── Validation du type MIME (image uniquement) ────────────────────────
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new FileException('Format de fichier non autorisé. Utilisez JPG, PNG ou WEBP.');
        }

        // ── Créer le dossier de destination s'il n'existe pas ─────────────────
        $targetDirectory = $this->uploadsBaseDir . '/' . $subDirectory;
        if (!is_dir($targetDirectory)) {
            if (!mkdir($targetDirectory, 0755, true)) {
                throw new FileException('Impossible de créer le dossier de destination.');
            }
        }

        // ── Générer un nom de fichier unique et sécurisé ──────────────────────
        // Le slugger nettoie le nom original (accents, espaces, caractères spéciaux)
        // uniqid() garantit l'unicité même si deux fichiers ont le même nom original
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename     = $this->slugger->slug($originalFilename);
        $extension        = $file->guessExtension();
        $newFilename      = $safeFilename . '-' . uniqid() . '.' . $extension;

        // ── Déplacer le fichier vers le dossier de destination ────────────────
        try {
            $file->move($targetDirectory, $newFilename);
        } catch (FileException $e) {
            throw new FileException('Erreur lors de l\'upload du fichier: ' . $e->getMessage());
        }

        // ── Supprimer l'ancien fichier si fourni (remplacement d'image) ───────
        if ($oldFilePath) {
            $this->deleteFile($oldFilePath);
        }

        // ── Retourner le chemin relatif pour stockage en base ─────────────────
        // Ex: 'equipements/tracteur-john-deere-65f3a9b2.jpg'
        return $subDirectory . '/' . $newFilename;
    }

    /**
     * Supprime un fichier uploadé à partir de son chemin relatif.
     *
     * Le chemin absolu est reconstruit en préfixant avec $uploadsBaseDir.
     * Utilise @unlink() pour ne pas lever d'exception si le fichier n'existe plus
     * (par exemple si déjà supprimé manuellement depuis le disque).
     *
     * @param string $relativePath  Chemin relatif du fichier (ex: 'equipements/photo-abc123.jpg')
     */
    public function deleteFile(string $relativePath): void
    {
        $fullPath = $this->uploadsBaseDir . '/' . $relativePath;
        if (file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * Construit l'URL publique complète d'un fichier uploadé.
     *
     * @param string $relativePath  Chemin relatif du fichier (ex: 'equipements/photo-abc123.jpg')
     *
     * @return string  URL complète (ex: '/uploads/equipements/photo-abc123.jpg')
     */
    public function getFileUrl(string $relativePath): string
    {
        return $this->uploadsBaseUrl . '/' . $relativePath;
    }

    /**
     * Vérifie que le dossier uploads existe et est accessible en écriture.
     *
     * Utile pour diagnostiquer des problèmes de permissions sur le serveur.
     * Appelable depuis un contrôleur de santé ou une commande Symfony de diagnostic.
     *
     * @return bool  true si le dossier existe et est writable
     */
    public function isUploadsDirectoryWritable(): bool
    {
        return is_dir($this->uploadsBaseDir) && is_writable($this->uploadsBaseDir);
    }
}
