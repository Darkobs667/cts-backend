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
use Illuminate\Support\Facades\Log; // pour les logs
use App\Models\Candidate;





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

        if ($user->role !== 'electeur' || $user->status !== 'Validé') {
            return response()->json(['message' => 'Seuls les électeurs validés peuvent voter.'], 403);
        }

        $position = Position::findOrFail($request->position_id);
        if (!$position->is_active) {
            return response()->json(['message' => 'Ce scrutin est fermé.'], 403);
        }

        if ($request->filled('candidate_id')) {
            $candidateIsEligible = Candidate::whereKey($request->candidate_id)
                ->where('position_id', $position->id)
                ->where('status', 'valide')
                ->exists();

            if (!$candidateIsEligible) {
                return response()->json([
                    'message' => 'Ce candidat ne peut pas recevoir de vote pour ce scrutin.'
                ], 422);
            }
        }

        // Store a keyed digest rather than an email address in the ballot table.
        $voterIdentifier = hash_hmac('sha256', (string) $user->id, config('app.key'));

        // Vérification de doublon
        $existing = Vote::where('position_id', $request->position_id)
                        ->whereIn('hash_session', [$voterIdentifier, $user->email])
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
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return response()->json(['message' => 'Vous avez déjà voté pour ce poste.'], 409);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'Le vote n’a pas pu être enregistré. Réessayez dans quelques instants.',
            ], 500);
        }
    }

    /**
     * Enregistre plusieurs choix en un seul bulletin confirmé par l'électeur.
     * Chaque poste reste vérifié indépendamment côté serveur.
     */
    public function batchStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'votes' => 'required|array|min:1|max:20',
            'votes.*.position_id' => 'required|integer|distinct|exists:positions,id',
            'votes.*.candidate_id' => 'nullable|integer|exists:candidates,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }
        if ($user->role !== 'electeur' || $user->status !== 'Validé') {
            return response()->json(['message' => 'Seuls les électeurs validés peuvent voter.'], 403);
        }

        $choices = $validator->validated()['votes'];
        $positions = Position::query()
            ->whereIn('id', collect($choices)->pluck('position_id'))
            ->get()
            ->keyBy('id');

        foreach ($choices as $choice) {
            $position = $positions->get($choice['position_id']);
            if (!$position?->is_active) {
                return response()->json(['message' => 'Un des scrutins sélectionnés est fermé. Actualisez la page.'], 409);
            }

            if (!empty($choice['candidate_id']) && !Candidate::query()
                ->whereKey($choice['candidate_id'])
                ->where('position_id', $position->id)
                ->where('status', 'valide')
                ->exists()) {
                return response()->json(['message' => 'Un des candidats sélectionnés ne peut pas recevoir ce vote.'], 422);
            }
        }

        $voterIdentifier = hash_hmac('sha256', (string) $user->id, config('app.key'));

        try {
            $votes = \Illuminate\Support\Facades\DB::transaction(function () use ($choices, $voterIdentifier, $user) {
                $positionIds = collect($choices)->pluck('position_id');
                $alreadyVoted = Vote::query()
                    ->whereIn('position_id', $positionIds)
                    ->whereIn('hash_session', [$voterIdentifier, $user->email])
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyVoted) {
                    throw new \RuntimeException('Un ou plusieurs scrutins ont déjà reçu votre vote. Actualisez la page.');
                }

                return collect($choices)->map(fn (array $choice) => Vote::create([
                    'position_id' => $choice['position_id'],
                    'candidate_id' => $choice['candidate_id'] ?? null,
                    'hash_session' => $voterIdentifier,
                ]));
            });
        } catch (\RuntimeException|\Illuminate\Database\UniqueConstraintViolationException $e) {
            return response()->json(['message' => 'Un ou plusieurs scrutins ont déjà reçu votre vote. Actualisez la page.'], 409);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Les votes n’ont pas pu être enregistrés. Réessayez dans quelques instants.'], 500);
        }

        foreach ($positions as $position) {
            $this->forgetCache('vote_results_' . $position->id);
        }
        $this->forgetCache('vote_results_all');
        $this->forgetCache('admin_global_stats');
        $this->forgetCache('positions_list');
        $this->forgetCache('positions_active_list');

        return response()->json([
            'message' => $votes->count() . ' vote(s) enregistré(s) avec succès.',
            'data' => $votes->values(),
        ], 201);
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

        $voterIdentifier = hash_hmac('sha256', (string) $user->id, config('app.key'));
        $votes = Vote::whereIn('hash_session', [$voterIdentifier, $user->email])
                    ->with('position')
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($vote) {
                        return [
                            'id'              => $vote->id,
                            'position_id'     => $vote->position_id,
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
                    ->whereIn('hash_session', [hash_hmac('sha256', (string) $user->id, config('app.key')), $user->email])
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

    /**
     * Voir tous les résultats (optimisé pour éviter les lenteurs Supabase/N+1)
     */
    public function allResults(): JsonResponse
    {
        try {
            // OPTIMISATION : Charger toutes les relations et les comptes en une seule passe
            $positions = Position::with([
                    'candidates' => function($query) {
                        $query->withCount('votes')->with('user');
                    }
                ])
                ->withCount('votes')
                ->get();

            $all = $positions->map(function ($position) {
                // Le tri se fait en mémoire pour ne pas multiplier les requêtes SQL
                $candidatesData = $position->candidates->where('status', 'valide')->map(function ($candidate) {
                    return [
                        'id'          => $candidate->id,
                        'name'        => ($candidate->user->first_name ?? '') . ' ' . ($candidate->user->last_name ?? ''),
                        'photo_path'  => $candidate->photo_path,
                        'votes_count' => $candidate->votes_count,
                    ];
                })->sortByDesc('votes_count')->values();

                return [
                    'id'          => $position->id,
                    'title'       => $position->title,
                    'is_active'   => (bool) $position->is_active,
                    'total_votes' => $position->votes_count,
                    'candidates'  => $candidatesData,
                ];
            });

            return response()->json(['success' => true, 'data' => $all]);

        } catch (\Exception $e) {
            Log::error('Erreur dans allResults : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Export PDF optimisé pour les environnements à forte latence
     */
    public function exportPDF()
    {
        // Récupérer tous les scrutins avec leurs candidats et votes de manière groupée (eager loading)
        $positions = Position::with([
                'candidates' => function($query) {
                    $query->withCount('votes')->with('user');
                }
            ])
            ->withCount('votes')
            ->get();

        $data = $positions->map(function ($position) {
            $candidatesData = $position->candidates->where('status', 'valide')->map(function ($candidate) {
                return [
                    'name'        => ($candidate->user->first_name ?? '') . ' ' . ($candidate->user->last_name ?? ''),
                    'votes_count' => $candidate->votes_count,
                ];
            })->sortByDesc('votes_count')->values();

            return [
                'title'       => $position->title,
                'is_active'   => (bool) $position->is_active,
                'total_votes' => $position->votes_count,
                'candidates'  => $candidatesData,
            ];
        });

        $pdfData = [
            'elections'    => $data,
            'generated_at' => now()->format('d/m/Y H:i:s'),
        ];

        $pdf = Pdf::loadView('pdf.results', $pdfData);
        return $pdf->download('resultats_scrutins.pdf');
    }

 /**
     * Vérifier si l'utilisateur a voté pour une position spécifique
     */
    public function checkVote($positionId)
    {
        $user = auth('api')->user();
        
        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }
        
        $hasVoted = Vote::whereIn('hash_session', [hash_hmac('sha256', (string) $user->id, config('app.key')), $user->email])
            ->where('position_id', $positionId)
            ->exists();
        
        return response()->json([
            'success' => true,
            'has_voted' => $hasVoted,
            'position_id' => $positionId
        ]);
    }
}
