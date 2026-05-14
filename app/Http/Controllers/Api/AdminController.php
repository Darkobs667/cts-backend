<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Position;
use App\Models\Vote;
use App\Traits\Cacheable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    use Cacheable;

    protected $statsCacheTtl = 300;

    /**
     * Stats globales de la plateforme
     */
    public function getStats(): JsonResponse
    {
        $stats = $this->rememberCache('admin_global_stats', function () {
            $totalInscrits    = User::where('role', 'electeur')->count();
            $votesEnCours     = Position::where('is_active', 1)->count();
            $votesClotures    = Position::where('is_active', 0)->count();
            $totalVotesUnique = Vote::distinct('hash_session')->count('hash_session');

            $participation = $totalInscrits > 0
                ? round(($totalVotesUnique / $totalInscrits) * 100)
                : 0;

            return [
                'totalInscrits' => $totalInscrits,
                'votesClotures' => $votesClotures,
                'votesEnCours'  => $votesEnCours,
                'participation' => $participation,
            ];
        }, $this->statsCacheTtl);

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * Participation détaillée par scrutin
     */
    public function getParticipationByPosition(): JsonResponse
    {
        $data = $this->rememberCache('admin_participation_by_position', function () {
            $totalInscrits = User::where('role', 'electeur')->count();
            $positions     = Position::all();

            $votesPerPosition = Vote::selectRaw('position_id, count(distinct hash_session) as votants')
                ->groupBy('position_id')
                ->pluck('votants', 'position_id');

            return $positions->map(function ($pos) use ($votesPerPosition, $totalInscrits) {
                $votants = $votesPerPosition[$pos->id] ?? 0;
                return [
                    'id'          => $pos->id,
                    'title'       => $pos->title,
                    'is_active'   => (bool) $pos->is_active,
                    'closes_at'   => $pos->closes_at,
                    'votants'     => $votants,
                    'inscrits'    => $totalInscrits,
                    'taux'        => $totalInscrits > 0 ? round(($votants / $totalInscrits) * 100) : 0,
                ];
            })->sortByDesc('votants')->values();
        }, $this->statsCacheTtl);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Scrutins expirant dans moins de 24h
     */
    public function getExpiringPositions(): JsonResponse
    {
        $expiring = Position::where('is_active', 1)
            ->whereNotNull('closes_at')
            ->where('closes_at', '>', now())
            ->where('closes_at', '<=', now()->addHours(24))
            ->orderBy('closes_at')
            ->get(['id', 'title', 'closes_at']);

        return response()->json(['success' => true, 'data' => $expiring]);
    }

    /**
     * Activer / désactiver un scrutin depuis le dashboard
     */
    public function togglePosition(Request $request, int $id): JsonResponse
    {
        $position = Position::findOrFail($id);
        $position->is_active = !$position->is_active;

        if ($position->is_active && !$position->started_at) {
            $position->started_at = now();
        }
        if (!$position->is_active) {
            $position->started_at = null;
        }

        $position->save();

        // Invalider les caches liés
        $this->forgetCache('admin_global_stats');
        $this->forgetCache('admin_participation_by_position');
        $this->forgetCache('positions_list');
        $this->forgetCache('vote_results_all');
        $this->forgetCache('vote_results_' . $id);

        Log::info('Admin toggle position', [
            'position_id' => $id,
            'is_active'   => $position->is_active,
            'admin_id'    => auth('api')->id(),
        ]);

        return response()->json([
            'success'   => true,
            'is_active' => $position->is_active,
            'message'   => $position->is_active ? 'Scrutin activé.' : 'Scrutin désactivé.',
        ]);
    }
}