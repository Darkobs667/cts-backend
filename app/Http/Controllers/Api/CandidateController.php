<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\CandidatService;
use App\Services\CandidatePhotoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;  // ← AJOUT (manquait dans l'original)
use App\Traits\Cacheable;

class CandidateController extends Controller
{
    use Cacheable;
    protected $candidatService;
    protected $cacheTtl = 300;  // 5 minutes

    public function __construct(
        CandidatService $candidatService,
        private readonly CandidatePhotoService $photoService,
    )
    {
        $this->candidatService = $candidatService;
    }

    /**
     * Liste tous les candidats d'un poste
     */
    public function index(Request $request): JsonResponse
    {
        // Construction d'une clé de cache unique basée sur les paramètres
        $positionId = $request->get('position_id', 'all');
        $perPage = min(max($request->integer('per_page', 20), 1), 50);
        $page = max($request->integer('page', 1), 1);
        $status = $request->get('status', 'valide');
        if (!in_array($status, ['valide', 'en_attente', 'refuse'], true)) {
            return response()->json(['message' => 'Statut de candidature invalide.'], 422);
        }

        if ($status !== 'valide' && auth('api')->user()?->role !== 'admin') {
            return response()->json(['message' => 'Accès administrateur requis.'], 403);
        }
        $cacheKey = 'candidates:v'.$this->cacheVersion('candidates')
            .":pos_{$positionId}:status_{$status}:page_{$page}:per_page_{$perPage}";
        
        $candidates = $this->rememberCache($cacheKey, function () use ($request, $status, $perPage) {
            $query = Candidate::with('user', 'position');

            if ($request->has('position_id')) {
                $query->where('position_id', $request->position_id);
            }

            $query->where('status', $status);

            // Convertir en array pour éviter les problèmes de sérialisation
            return $query->orderByDesc('id')->paginate($perPage)->toArray();
        }, $this->cacheTtl);

        return response()->json([
            'success' => true,
            'data' => $candidates['data'],
            'meta' => collect($candidates)->except('data')->all(),
        ]);
    }

    /**
     * Créer une nouvelle candidature (Admin seulement)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'position_id' => 'required|exists:positions,id',
            'slogan' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:5000',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // Gestion de l'upload de la photo
        if ($request->hasFile('photo')) {
            $data = array_merge($data, $this->storePhoto($request->file('photo')));
        }

        $candidate = $this->candidatService->create($data);

        // ← VIDER LE CACHE APRÈS CRÉATION
        $this->forgetCacheByPrefix('candidates_list');
        $this->forgetCache('admin_global_stats');

        return response()->json([
            'message' => 'Candidat créé avec succès',
            'data' => $candidate,
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $candidate = Candidate::with(['user', 'position'])->findOrFail($id);
        if ($candidate->status !== 'valide' && auth('api')->user()?->role !== 'admin') {
            abort(404);
        }

        return response()->json(['success' => true, 'data' => $candidate]);
    }

    /**
     * Récupérer le profil candidat de l'utilisateur connecté
     */
    public function profile(): JsonResponse
    {
        try {
            $profile = $this->candidatService->getProfile();
            return response()->json(['data' => $profile]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }

    /**
     * Mettre à jour une candidature
     */
    public function update(Request $request, $id)
    {
        $candidate = Candidate::findOrFail($id);
        
        $data = $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'position_id' => 'sometimes|exists:positions,id',
            'slogan' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:5000',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $previousPhotoPublicId = $candidate->photo_public_id;
        $previousPhotoPath = $candidate->photo_path;
        if ($request->hasFile('photo')) {
            $data = array_merge($data, $this->storePhoto($request->file('photo')));
        }

        $result = $this->candidatService->update($candidate, $data);

        if ($result && $request->hasFile('photo')) {
            if ($previousPhotoPublicId) {
                $this->photoService->delete($previousPhotoPublicId);
            } elseif ($previousPhotoPath) {
                Storage::disk('public')->delete($previousPhotoPath);
            }
        }

        // ← VIDER LE CACHE APRÈS MODIFICATION
        $this->forgetCacheByPrefix('candidates_list');
        $this->forgetCache("candidate_{$id}");
        $this->forgetCache('admin_global_stats');

        return response()->json([
            'message' => 'Candidat mis à jour avec succès',
            'data' => $candidate->fresh(['user', 'position']),
        ]);
    }

    /**
     * Supprimer une candidature (Admin seulement)
     */
    public function destroy($id)
    {
        $candidate = Candidate::findOrFail($id);
        $result = $this->candidatService->delete($candidate);

        if ($result) {
            // ← VIDER LE CACHE APRÈS SUPPRESSION
            $this->forgetCacheByPrefix('candidates_list');
            $this->forgetCache("candidate_{$id}");
            $this->forgetCache('admin_global_stats');
            
            return response()->json(['message' => 'Candidat supprimé avec succès']);
        }
        return response()->json(['message' => 'Erreur lors de la suppression'], 500);
    }

    public function apply(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $validator = Validator::make($request->all(), [
            'position_id' => 'required|exists:positions,id',
            'bio'         => 'nullable|string|max:5000',
            'slogan'      => 'nullable|string|max:255',
            'photo'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Vérifier s'il a déjà postulé pour ce poste
        $existing = Candidate::where('user_id', $user->id)
                             ->where('position_id', $request->position_id)
                             ->first();
        if ($existing) {
            return response()->json(['message' => 'Vous avez déjà postulé à ce poste.'], 409);
        }

        $data = $request->only('position_id', 'bio', 'slogan');
        $data['user_id'] = $user->id;
        $data['status'] = 'en_attente';

        if ($request->hasFile('photo')) {
            $data = array_merge($data, $this->storePhoto($request->file('photo')));
        }

        $candidate = Candidate::create($data);

        // ← VIDER LE CACHE APRÈS CANDIDATURE
        $this->forgetCacheByPrefix('candidates_list');
        $this->forgetCache('admin_global_stats');

        return response()->json([
            'message' => 'Votre candidature a été enregistrée. Elle sera examinée par l\'administration.',
            'data'    => $candidate,
        ], 201);
    }

    public function approve($id)
    {
        $candidate = Candidate::findOrFail($id);
        if ($candidate->status !== 'en_attente') {
            return response()->json(['message' => 'Ce candidat n\'est pas en attente.'], 400);
        }
        $candidate->status = 'valide';
        $candidate->save();

        // ← VIDER LE CACHE APRÈS APPROBATION
        $this->forgetCacheByPrefix('candidates_list');
        $this->forgetCache("candidate_{$id}");
        $this->forgetCache('admin_global_stats');

        return response()->json(['message' => 'Candidature validée.']);
    }

    public function reject($id)
    {
        $candidate = Candidate::findOrFail($id);
        if ($candidate->status !== 'en_attente') {
            return response()->json(['message' => 'Ce candidat n\'est pas en attente.'], 400);
        }
        $candidate->status = 'refuse';
        $candidate->save();

        // ← VIDER LE CACHE APRÈS REFUS
        $this->forgetCacheByPrefix('candidates_list');
        $this->forgetCache("candidate_{$id}");
        $this->forgetCache('admin_global_stats');

        return response()->json(['message' => 'Candidature refusée.']);
    }

    /**
     * Méthode utilitaire pour vider les caches par préfixe
     */
    protected function forgetCacheByPrefix($prefix)
    {
        $this->bumpCacheVersion('candidates');
    }

    private function storePhoto($photo): array
    {
        $asset = $this->photoService->upload($photo);

        return [
            'photo_path' => $asset['secure_url'],
            'photo_public_id' => $asset['public_id'],
        ];
    }
}
