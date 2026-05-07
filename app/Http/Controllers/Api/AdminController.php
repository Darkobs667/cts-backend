<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Position;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function getStats(): JsonResponse
{
    try {
        // 1. Nombre total d'électeurs
        $totalInscrits = User::where('role', 'electeur')->count();

        // 2. Statistiques des scrutins
        $votesEnCours = Position::where('is_active', 1)->count();
        $votesClotures = Position::where('is_active', 0)->count();

        // 3. Calcul de la participation
        // Utilise hash_session (qui identifie chaque votant unique) au lieu de user_id
        $totalVotesUnique = Vote::distinct('hash_session')->count('hash_session');
        
        $participation = $totalInscrits > 0 
            ? round(($totalVotesUnique / $totalInscrits) * 100) 
            : 0;

        return response()->json([
            'success' => true,
            'message' => 'Statistiques récupérées',
            'data' => [
                'totalInscrits' => $totalInscrits,
                'votesClotures' => $votesClotures,
                'votesEnCours'  => $votesEnCours,
                'participation' => $participation
            ]
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur technique : ' . $e->getMessage()
        ], 500);
    }
}
}