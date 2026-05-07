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

            // 2. Statistiques des scrutins (Vérifie bien que la table 'positions' a une colonne 'status')
            // Si la colonne 'status' n'existe pas encore, mets ces lignes en commentaire
            $votesEnCours = Position::where('is_active', 1)->count();
            $votesClotures = Position::where('is_active', 0)->count();

            // 3. Calcul de la participation
            $totalVotesUnique = Vote::distinct('user_id')->count();
            $participation = $totalInscrits > 0 
                ? round(($totalVotesUnique / $totalInscrits) * 100) 
                : 0;

            // Utilisation de la méthode standard de Laravel
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
            // Si ça plante, Laravel nous dira pourquoi dans Postman
            return response()->json([
                'success' => false,
                'message' => 'Erreur technique : ' . $e->getMessage()
            ], 500);
        }
    }
}