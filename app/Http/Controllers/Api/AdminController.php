<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Position;
use App\Models\Vote;
use App\Traits\Cacheable;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    use Cacheable;

    protected $statsCacheTtl = 300;  // 5 minutes

    public function getStats(): JsonResponse
    {
        $stats = $this->rememberCache('admin_global_stats', function () {
            $totalInscrits = User::where('role', 'electeur')->count();
            $votesEnCours = Position::where('is_active', 1)->count();
            $votesClotures = Position::where('is_active', 0)->count();
            $totalVotesUnique = Vote::distinct('hash_session')->count('hash_session');
            
            $participation = $totalInscrits > 0 
                ? round(($totalVotesUnique / $totalInscrits) * 100) 
                : 0;

            return [
                'totalInscrits' => $totalInscrits,
                'votesClotures' => $votesClotures,
                'votesEnCours'  => $votesEnCours,
                'participation' => $participation
            ];
        }, $this->statsCacheTtl);

        return response()->json([
            'success' => true,
            'message' => 'Statistiques récupérées',
            'data' => $stats
        ]);
    }

    /**
     * Méthode pour forcer le rafraîchissement du cache des stats
     * Utile si on veut des stats à jour immédiatement
     */
    public function refreshStats(): JsonResponse
    {
        $this->forgetCache('admin_global_stats');
        
        // Générer les nouvelles stats
        $stats = [
            'totalInscrits' => User::where('role', 'electeur')->count(),
            'votesEnCours' => Position::where('is_active', 1)->count(),
            'votesClotures' => Position::where('is_active', 0)->count(),
            'participation' => 0
        ];
        
        return response()->json([
            'success' => true,
            'message' => 'Cache des statistiques rafraîchi',
            'data' => $stats
        ]);
    }
}