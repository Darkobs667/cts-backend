<?php

namespace App\Http\Controllers\Api;

use App\Models\Vote;
use App\Http\Controllers\Controller;
use App\Services\VoteService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf; 
use App\Traits\Cacheable;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Models\Candidate;
use App\Services\AuthServices;





class VoteController extends Controller
{
    use Cacheable;
    protected $voteService;
    protected $resultsCacheTtl = 120; // 2 minutes

    public function __construct(VoteService $voteService)
    {
        $this->voteService = $voteService;
    }

    /**
     * Soumettre un vote
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'position_id'  => 'required|exists:positions,id',
            'candidate_id' => 'nullable|exists:candidates,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        $voterIdentifier = AuthServices::voterHash($user);

        // Vérification de doublon
        $existing = Vote::where('position_id', $request->position_id)
                        ->where('hash_session', $voterIdentifier)
                        ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Vous avez déjà voté pour ce poste.'
            ], 409);
        }

        // Insertion manuelle
        try {
            $vote = Vote::create([
                'position_id'  => $request->position_id,
                'candidate_id' => $request->candidate_id,
                'hash_session' => $voterIdentifier,
            ]);

            // ← VIDER LES CACHES APRÈS UN VOTE
            $this->forgetCache('vote_results_all');
            $this->forgetCache('vote_results_' . $request->position_id);
            $this->forgetCache('admin_global_stats');
            $this->forgetCache('positions_list');
            $this->forgetCache('positions_active_list');

            return response()->json([
                'message' => 'Votre vote a été enregistré avec succès.',
                'data'    => $vote,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Voir les résultats globaux des votes
     */
    public function results(Request $request): JsonResponse
    {
        $positionId = $request->get('position_id', 'all');
        $cacheKey = "vote_results_{$positionId}";
        
        $results = $this->rememberCache($cacheKey, function () use ($positionId) {
            $data = $this->voteService->getResults($positionId !== 'all' ? (int)$positionId : null);
            return json_decode(json_encode($data), true);
        }, $this->resultsCacheTtl);

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * Récupérer les votes de l'utilisateur avec détails complets
     * Utile pour la page historique des reçus
     */
    public function myVotes(): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $votes = Vote::where('hash_session', AuthServices::voterHash($user))
                    ->with('position', 'candidate.user')
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($vote) {
                        $candidateName = 'Vote blanc';
                        $photoPath = null;
                        
                        if ($vote->candidate) {
                            $candidateUser = $vote->candidate->user;
                            $candidateName = $candidateUser ? ($candidateUser->first_name . ' ' . $candidateUser->last_name) : 'Candidat';
                            $photoPath = $vote->candidate->photo_path ? asset('storage/' . $vote->candidate->photo_path) : null;
                        }
                        
                        return [
                            'id'              => $vote->id,
                            'position_id'     => $vote->position_id,
                            'election_title'   => $vote->position->title ?? 'Scrutin inconnu',
                            'candidate_name'   => $candidateName,
                            'photo_path'       => $photoPath,
                            'date_voted'       => $vote->created_at->toIsoString(),
                            'transaction_ref'  => 'CTS-' . strtoupper(substr(md5($vote->id), 0, 8)),
                        ];
                    });

        return response()->json($votes);
    }

    public function receipt($voteId)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $vote = Vote::where('id', $voteId)
                    ->where('hash_session', AuthServices::voterHash($user))
                    ->with('position', 'candidate.user')
                    ->first();

        if (!$vote) {
            return response()->json(['message' => 'Vote introuvable ou non autorisé'], 404);
        }

        // Récupérer les infos du candidat
        $candidate = $vote->candidate;
        $candidateName = 'Vote blanc';
        $photoPath = null;
        
        if ($candidate) {
            $candidateUser = $candidate->user;
            $candidateName = ($candidateUser ? $candidateUser->first_name . ' ' . $candidateUser->last_name : 'Candidat');
            $photoPath = $candidate->photo_path ? asset('storage/' . $candidate->photo_path) : null;
        }

        // Génération d'un PDF amélioré avec les informations du vote
        $data = [
            'election' => $vote->position->title ?? 'Scrutin inconnu',
            'date'     => $vote->created_at->format('d/m/Y à H:i'),
            'ref'      => 'CTS-' . strtoupper(substr(md5($vote->id), 0, 8)),
            'electeur' => $user->first_name . ' ' . $user->last_name,
            'candidat_name' => $candidateName,
            'photo_path' => $photoPath,
        ];

        $pdf = Pdf::loadView('pdf.receipt', $data);

        return $pdf->download('Recu_Vote_' . $vote->id . '.pdf');
    }

    public function allResults(): JsonResponse
    {
        try {
            $positions = Position::all();
            $all = [];

            foreach ($positions as $position) {
                $onlineTotal = Vote::where('position_id', $position->id)->count();
                $candidates  = Candidate::where('position_id', $position->id)->get();
                $hasPhysical = $candidates->sum('physical_votes') > 0;

                $candidatesData = [];
                foreach ($candidates as $candidate) {
                    $onlineVotes   = Vote::where('candidate_id', $candidate->id)->count();
                    $physicalVotes = (int) $candidate->physical_votes;
                    $totalVotes    = $onlineVotes + $physicalVotes;

                    $user     = User::find($candidate->user_id);
                    $fullName = $user ? ($user->first_name . ' ' . $user->last_name) : 'Candidat';

                    $candidatesData[] = [
                        'id'             => $candidate->id,
                        'name'           => $fullName,
                        'photo_path'     => $candidate->photo_path,
                        'online_votes'   => $onlineVotes,
                        'physical_votes' => $physicalVotes,
                        'votes_count'    => $totalVotes,
                    ];
                }

                usort($candidatesData, fn($a, $b) => $b['votes_count'] <=> $a['votes_count']);

                $physicalTotal = array_sum(array_column($candidatesData, 'physical_votes'));

                $all[] = [
                    'id'             => $position->id,
                    'title'          => $position->title,
                    'is_active'      => (bool) $position->is_active,
                    'closes_at'      => $position->closes_at,
                    'quorum'         => $position->quorum,
                    'quorum_reached' => $position->quorum ? ($onlineTotal + $physicalTotal) >= $position->quorum : null,
                    'has_physical'   => $hasPhysical,
                    'online_total'   => $onlineTotal,
                    'physical_total' => $physicalTotal,
                    'total_votes'    => $onlineTotal + $physicalTotal,
                    'candidates'     => $candidatesData,
                ];
            }

            return response()->json(['success' => true, 'data' => $all]);

        } catch (\Exception $e) {
            Log::error('Erreur dans allResults : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

public function exportPDF()
{
    $positions = Position::all();
    $data = [];

    foreach ($positions as $position) {
        $onlineTotal = Vote::where('position_id', $position->id)->count();
        $candidates  = Candidate::where('position_id', $position->id)->get();
        $candidatesData = [];

        foreach ($candidates as $candidate) {
            $onlineVotes   = Vote::where('candidate_id', $candidate->id)->count();
            $physicalVotes = (int) $candidate->physical_votes;
            $totalVotes    = $onlineVotes + $physicalVotes;
            $user          = User::find($candidate->user_id);
            $fullName      = $user ? ($user->first_name . ' ' . $user->last_name) : 'Candidat';
            $candidatesData[] = [
                'name'           => $fullName,
                'online_votes'   => $onlineVotes,
                'physical_votes' => $physicalVotes,
                'votes_count'    => $totalVotes,
            ];
        }

        usort($candidatesData, fn($a, $b) => $b['votes_count'] <=> $a['votes_count']);

        $physicalTotal = array_sum(array_column($candidatesData, 'physical_votes'));

        $data[] = [
            'title'          => $position->title,
            'is_active'      => $position->is_active,
            'online_total'   => $onlineTotal,
            'physical_total' => $physicalTotal,
            'total_votes'    => $onlineTotal + $physicalTotal,
            'candidates'     => $candidatesData,
        ];
    }

    $pdfData = [
        'elections'    => $data,
        'generated_at' => now()->format('d/m/Y H:i:s'),
    ];

    $pdf = Pdf::loadView('pdf.results', $pdfData);
    return $pdf->download('resultats_scrutins.pdf');
}
}