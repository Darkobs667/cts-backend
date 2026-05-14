<?php

namespace App\Services;

use App\Models\Vote;
use App\Models\Candidate;
use App\Models\Position;
use App\Services\AuthServices;
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

    $voterIdentifier = AuthServices::voterHash($user);

    // Vérifie si l'utilisateur a déjà voté pour ce poste
    $existing = Vote::where('position_id', $positionId)
                    ->where('hash_session', $voterIdentifier)
                    ->first();

    if ($existing) {
        throw new \Exception('Vous avez déjà voté pour ce poste.', 409);
    }

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
        return Position::with(['candidates.user'])
            ->where('is_active', true)
            ->get()
            ->map(function ($position) {
                return [
                    'id'         => $position->id,
                    'title'      => $position->title,
                    'is_active'  => $position->is_active,
                    'started_at' => $position->started_at,
                    // Pas de votes_count ni de détail candidats tant que actif
                    'candidates' => $position->candidates
                        ->where('status', 'valide')
                        ->map(function ($candidate) {
                            return [
                                'id'         => $candidate->id,
                                'name'       => $candidate->user->first_name . ' ' . $candidate->user->last_name,
                                'photo_path' => $candidate->photo_path,
                                'bio'        => $candidate->bio,
                                'slogan'     => $candidate->slogan,
                            ];
                            // Aucun votes_count retourné tant que le scrutin est actif
                        })
                ];
            })
            ->values();
    }


    /**
     * Vérifier quels postes l'utilisateur a déjà voté.
     */
    public function getUserVotes(): Collection
    {
        $user = auth('api')->user();
        if (!$user) return collect();

        return Vote::where('hash_session', AuthServices::voterHash($user))
                   ->pluck('position_id');
    }
}