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

        $voterIdentifier = $user->email;

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
            if ($positionId !== 'all') {
                $data = $this->voteService->getResults($positionId);
            } else {
                $data = $this->voteService->getResults();
            }
            
            // Convertir en array pour éviter les problèmes de sérialisation
            return json_decode(json_encode($data), true);
        }, $this->resultsCacheTtl);

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * Récupérer les IDs des postes pour lesquels l'utilisateur a déjà voté
     * Utile pour griser les boutons de vote côté Frontend 
     */
    public function myVotes(): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $votes = Vote::where('hash_session', $user->email)
                    ->with('position')
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($vote) {
                        return [
                            'id'              => $vote->id,
                            'election_title'   => $vote->position->title ?? 'Scrutin inconnu',
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
                    ->where('hash_session', $user->email)
                    ->with('position', 'candidate.user')
                    ->first();

        if (!$vote) {
            return response()->json(['message' => 'Vote introuvable ou non autorisé'], 404);
        }

        // Génération d'un PDF simple avec les informations du vote
        $data = [
            'election' => $vote->position->title ?? 'Scrutin inconnu',
            'date'     => $vote->created_at->format('d/m/Y à H:i'),
            'ref'      => 'CTS-' . strtoupper(substr(md5($vote->id), 0, 8)),
            'electeur' => $user->first_name . ' ' . $user->last_name,
        ];

        $pdf = Pdf::loadView('pdf.receipt', $data);

        return $pdf->download('Recu_Vote_' . $vote->id . '.pdf');
    }
}