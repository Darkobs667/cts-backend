<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait Cacheable
{
    /**
     * Récupérer depuis le cache ou exécuter la requête
     */
    protected function rememberCache(string $key, \Closure $callback, int $ttl = 300)
    {
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Vider le cache pour une clé spécifique
     */
    protected function forgetCache(string $key): void
    {
        Cache::forget($key);
    }
}