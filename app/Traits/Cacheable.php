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

    /**
     * Vider le cache pour un préfixe (toutes les combinaisons pos/status)
     */
    protected function forgetCacheByPrefix(string $prefix, array $positions = [], array $statuses = []): void
    {
        if (empty($statuses)) {
            $statuses = ['all', 'en_attente', 'valide', 'refuse'];
        }
        if (empty($positions)) {
            $positions = \App\Models\Position::pluck('id')->toArray();
            $positions[] = 'all';
        }
        foreach ($positions as $posId) {
            foreach ($statuses as $status) {
                Cache::forget("{$prefix}_pos_{$posId}_status_{$status}");
            }
        }
    }