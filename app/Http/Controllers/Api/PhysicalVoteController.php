<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Position;
use App\Traits\Cacheable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhysicalVoteController extends Controller
{
    use Cacheable;

    /**
     * Saisir les votes physiques pour tous les candidats d'un poste
     * Body: { "votes": { "candidate_id": nb_votes, ... } }
     */
    public function store(Request $request, $positionId): JsonResponse
    {
        $position = Position::findOrFail($positionId);

        $request->validate([
            'votes'   => 'required|array',
            'votes.*' => 'integer|min:0',
        ]);

        foreach ($request->votes as $candidateId => $count) {
            Candidate::where('id', $candidateId)
                ->where('position_id', $positionId)
                ->update(['physical_votes' => $count]);
        }

        // Vider les caches liés
        $this->forgetCache('vote_results_all');
        $this->forgetCache('vote_results_' . $positionId);
        $this->forgetCache('admin_global_stats');

        return response()->json(['success' => true, 'message' => 'Votes physiques enregistrés.']);
    }
}
