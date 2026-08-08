<?php

return [
    // Local development stores files in storage/app/public (served at
    // /storage). Production must use Cloudinary so uploads survive deploys.
    'driver' => env(
        'CANDIDATE_PHOTO_STORAGE',
        env('APP_ENV') === 'production' ? 'cloudinary' : 'local',
    ),

    // Format: cloudinary://<api_key>:<api_secret>@<cloud_name>
    'url' => env('CLOUDINARY_URL'),
    'folder' => env('CLOUDINARY_FOLDER', 'cts/candidates'),
];
