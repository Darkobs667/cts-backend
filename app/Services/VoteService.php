<?php

namespace App\Services;

use App\Models\Vote;
use App\Models\Candidate;
use App\Models\Position;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class VoteService 
{
    /**
     * Enregistrer un vote pour un candidat sur un poste spécifique.
     */
public function castVote(int $positionId, ?int $candidateId): Vote
{
    $user = auth('api')->user();
    if (!$user) {
        throw new \Exception('Utilisateur non authentifié', 401);
    }

    // Utilise l'email de l'utilisateur comme identifiant de session
    $voterIdentifier = $user->email;

    // Vérifie si l'utilisateur a déjà voté pour ce poste
    $existing = Vote::where('position_id', $positionId)
                    ->where('hash_session', $voterIdentifier)
                    ->first();

    if ($existing) {
        throw new \Exception('Vous avez déjà voté pour ce poste.', 409);
    }

    \Log::info('Données du vote', [
    'position_id' => $positionId,
    'candidate_id' => $candidateId,
    'hash_session' => $voterIdentifier,
]);

    // Création du vote AVEC hash_session
    return Vote::create([
        'position_id'   => $positionId,
        'candidate_id'  => $candidateId,
        'hash_session'  => $voterIdentifier,
    ]);
}

    /**
     * Obtenir les résultats actuels pour tous les postes.
     */
    public function getResults(): Collection
    {
        return Position::with([
                'candidates' => function($query) {
                    $query->withCount('votes')->with('user');
                }
            ])
            ->withCount('votes')
            ->where('is_active', true)
            ->get()
            ->map(function ($position) {
                return [
                    'id'          => $position->id,
                    'title'       => $position->title,
                    'is_active'   => (bool) $position->is_active,
                    'started_at'  => $position->started_at,
                    'total_votes' => $position->votes_count,
                    'candidates'  => $position->candidates->map(function ($candidate) {
                        $fullName = $candidate->user 
                            ? trim($candidate->user->first_name . ' ' . $candidate->user->last_name)
                            : 'Candidat inconnu';

                        return [
                            'name'        => $fullName,
                            'votes_count' => $candidate->votes_count,
                            'photo_path'  => $candidate->photo_path,
                            'bio'         => $candidate->bio,
                            'profession'  => $candidate->user->role ?? 'Électeur',
                        ];
                    })
                ];
            })
            ->sortByDesc('total_votes')
            ->values();
    }


    /**
     * Vérifier quels postes l'utilisateur a déjà voté.
     */
    public function getUserVotes(): Collection
    {
        $user = auth('api')->user();
        if (!$user) return collect();

        return Vote::where('hash_session', $user->email)
                   ->pluck('position_id');
    }
}