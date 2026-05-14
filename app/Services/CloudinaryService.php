<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Stockage d'images en base64 — zéro configuration externe.
 * L'image est encodée en base64 et stockée directement en base de données.
 * Compatible avec le reste du code (même interface upload/delete).
 */
class CloudinaryService
{
    /**
     * Encode l'image en base64 data URI et la retourne.
     * La "suppression" n'est pas nécessaire (gérée par la DB).
     */
    public function upload(UploadedFile $file, string $folder = 'candidates'): ?string
    {
        $mime    = $file->getMimeType();
        $data    = base64_encode(file_get_contents($file->getRealPath()));
        return "data:{$mime};base64,{$data}";
    }

    public function delete(string $publicIdOrUrl): void
    {
        // Rien à faire — la suppression est gérée par la DB
    }
}
