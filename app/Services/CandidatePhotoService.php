<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CandidatePhotoService
{
    private function usesCloudinary(): bool
    {
        return config('cloudinary.driver') === 'cloudinary';
    }

    private function client(): Cloudinary
    {
        $url = config('cloudinary.url');

        if (blank($url)) {
            throw new RuntimeException('CLOUDINARY_URL doit être configurée pour stocker les photos en production.');
        }

        return new Cloudinary($url);
    }

    /** @return array{public_id: ?string, secure_url: string} */
    public function upload(UploadedFile $photo): array
    {
        if (config('cloudinary.driver') === 'local') {
            $path = $photo->store('candidates', 'public');
            return ['public_id' => null, 'secure_url' => $path];
        }

        if (!$this->usesCloudinary()) {
            throw new RuntimeException('Le pilote de stockage des photos est invalide.');
        }

        $asset = $this->client()->uploadApi()->upload($photo->getRealPath(), [
            'resource_type' => 'image',
            'folder' => config('cloudinary.folder'),
            'public_id' => (string) Str::uuid(),
            'overwrite' => false,
            'unique_filename' => false,
        ]);

        return [
            'public_id' => $asset['public_id'],
            'secure_url' => $asset['secure_url'],
        ];
    }

    public function delete(?string $publicId): void
    {
        if (filled($publicId) && $this->usesCloudinary()) {
            $this->client()->uploadApi()->destroy($publicId, ['resource_type' => 'image', 'invalidate' => true]);
        }
    }

    public static function deliveryUrl(?string $originalUrl): ?string
    {
        if (blank($originalUrl)) {
            return null;
        }
        if (!str_starts_with($originalUrl, 'https://res.cloudinary.com/')) {
            return $originalUrl;
        }

        return str_replace(
            '/image/upload/',
            '/image/upload/f_auto/q_auto/c_fill,g_auto,w_256,h_256/',
            $originalUrl,
        );
    }
}
