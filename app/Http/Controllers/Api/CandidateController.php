<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\CandidatService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Traits\Cacheable;

class CandidateController extends Controller
{
    use Cacheable;  // ← AJOUTE DE CACHE
    protected $candidatService;
     protected $cacheTtl = 300;  // ←  (5 minutes)

    public function __construct(CandidatService $candidatService)
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
    $status = $request->get('status', 'all');
    $cacheKey = "candidates_list_pos_{$positionId}_status_{$status}";
    
    $candidates = $this->rememberCache($cacheKey, function () use ($request) {
        $query = Candidate::with('user', 'position');

        if ($request->has('position_id')) {
            $query->where('position_id', $request->position_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Convertir en array pour éviter les problèmes de sérialisation
        return $query->get()->toArray();
    }, $this->cacheTtl);

    return response()->json([
        'success' => true,
        'data' => $candidates,
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
        'slogan' => 'nullable|string',
        'bio' => 'nullable|string',
        'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // validation image
    ]);

    // Gestion de l'upload de la photo
    if ($request->hasFile('photo')) {
        $data['photo_path'] = $request->file('photo')->store('candidates', 'public');
    }

    $candidate = $this->candidatService->create($data);

    return response()->json([
        'message' => 'Candidat créé avec succès',
        'data' => $candidate,
    ], 201);
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
        'slogan' => 'nullable|string',
        'bio' => 'nullable|string',
        'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    ]);

    if ($request->hasFile('photo')) {
        // Supprimer l'ancienne photo si elle existe
        if ($candidate->photo_path) {
            Storage::disk('public')->delete($candidate->photo_path);
        }
        $data['photo_path'] = $request->file('photo')->store('candidates', 'public');
    }

    $result = $this->candidatService->update($candidate, $data);

    return response()->json(['message' => 'Candidat mis à jour avec succès']);
}

    /**
     * Supprimer une candidature (Admin seulement)
     */
    public function destroy($id)
{
    $candidate = Candidate::findOrFail($id);
    $result = $this->candidatService->delete($candidate);

    if ($result) {
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
        'bio'         => 'nullable|string',
        'slogan'      => 'nullable|string',
        'photo'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
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
        $data['photo_path'] = $request->file('photo')->store('candidates', 'public');
    }

    $candidate = Candidate::create($data);

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

    return response()->json(['message' => 'Candidature refusée.']);
}

}