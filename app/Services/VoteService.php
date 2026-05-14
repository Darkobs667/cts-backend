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
    public function getResults(?int $positionId = null): Collection
    {
        $query = Position::with(['candidates.user'])->where('is_active', true);
        if ($positionId) {
            $query->where('id', $positionId);
        }

        return $query->get()->map(function ($position) {
            $validCandidates = $position->candidates->where('status', 'valide');
            $totalVotes = Vote::where('position_id', $position->id)->count();

            $candidates = $validCandidates->map(function ($candidate) use ($totalVotes) {
                $votes = Vote::where('candidate_id', $candidate->id)->count();
                $pct   = $totalVotes > 0 ? round(($votes / $totalVotes) * 100, 1) : 0;
                return [
                    'id'          => $candidate->id,
                    'name'        => $candidate->user->first_name . ' ' . $candidate->user->last_name,
                    'photo_path'  => $candidate->photo_path,
                    'bio'         => $candidate->bio,
                    'slogan'      => $candidate->slogan,
                    'votes_count' => $votes,
                    'percentage'  => $pct,
                ];
            })->sortByDesc('votes_count')->values();

            return [
                'id'          => $position->id,
                'title'       => $position->title,
                'is_active'   => $position->is_active,
                'started_at'  => $position->started_at,
                'total_votes' => $totalVotes,
                'candidates'  => $candidates,
            ];
        })->values();
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