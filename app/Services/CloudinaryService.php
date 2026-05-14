<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class CloudinaryService
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;

    public function __construct()
    {
        $this->cloudName = env('CLOUDINARY_CLOUD_NAME', '');
        $this->apiKey    = env('CLOUDINARY_API_KEY', '');
        $this->apiSecret = env('CLOUDINARY_API_SECRET', '');
    }

    public function upload(UploadedFile $file, string $folder = 'candidates'): ?string
    {
        if (!$this->cloudName || !$this->apiKey || !$this->apiSecret) {
            // Fallback sur le stockage local si Cloudinary non configuré
            return $file->store($folder, 'public');
        }

        $timestamp = time();
        $params    = "folder={$folder}&timestamp={$timestamp}";
        $signature = sha1($params . $this->apiSecret);

        $response = Http::attach(
            'file', file_get_contents($file->getRealPath()), $file->getClientOriginalName()
        )->post("https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload", [
            'api_key'   => $this->apiKey,
            'timestamp' => $timestamp,
            'folder'    => $folder,
            'signature' => $signature,
        ]);

        if ($response->successful()) {
            return $response->json('secure_url');
        }

        // Fallback local si Cloudinary échoue
        return $file->store($folder, 'public');
    }

    public function delete(string $publicIdOrUrl): void
    {
        if (!$this->cloudName || !$this->apiKey || !$this->apiSecret) return;
        if (str_starts_with($publicIdOrUrl, 'http')) return; // URL locale, rien à faire

        $timestamp = time();
        $params    = "public_id={$publicIdOrUrl}&timestamp={$timestamp}";
        $signature = sha1($params . $this->apiSecret);

        Http::post("https://api.cloudinary.com/v1_1/{$this->cloudName}/image/destroy", [
            'public_id' => $publicIdOrUrl,
            'api_key'   => $this->apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);
    }
}
