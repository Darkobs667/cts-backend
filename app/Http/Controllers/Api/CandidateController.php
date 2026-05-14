<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\CandidatService;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Traits\Cacheable;

class CandidateController extends Controller
{
    use Cacheable;

    protected $candidatService;
    protected $cloudinary;
    protected $cacheTtl = 300;

    public function __construct(CandidatService $candidatService, CloudinaryService $cloudinary)
    {
        $this->candidatService = $candidatService;
        $this->cloudinary      = $cloudinary;
    }

    public function index(Request $request): JsonResponse
    {
        $positionId = $request->get('position_id', 'all');
        $status     = $request->get('status', 'all');
        $cacheKey   = "candidates_list_pos_{$positionId}_status_{$status}";

        $candidates = $this->rememberCache($cacheKey, function () use ($request) {
            $query = Candidate::with('user', 'position');
            if ($request->has('position_id')) $query->where('position_id', $request->position_id);
            if ($request->has('status'))      $query->where('status', $request->status);
            return $query->get()->toArray();
        }, $this->cacheTtl);

        return response()->json(['success' => true, 'data' => $candidates]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id'     => 'required|exists:users,id',
            'position_id' => 'required|exists:positions,id',
            'slogan'      => 'nullable|string',
            'bio'         => 'nullable|string',
            'photo'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $this->cloudinary->upload($request->file('photo'));
        }

        try {
            $candidate = $this->candidatService->create($data);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 400;
            return response()->json(['message' => $e->getMessage()], $code);
        }

        $this->forgetCacheByPrefix('candidates_list');
        $this->forgetCache('admin_global_stats');

        return response()->json(['message' => 'Candidat créé avec succès', 'data' => $candidate], 201);
    }

    public function profile(): JsonResponse
    {
        try {
            return response()->json(['data' => $this->candidatService->getProfile()]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }

    public function update(Request $request, $id)
    {
        $candidate = Candidate::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'user_id'     => 'sometimes|exists:users,id',
            'position_id' => 'sometimes|exists:positions,id',
            'slogan'      => 'nullable|string',
            'bio'         => 'nullable|string',
            'photo'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('photo')) {
            if ($candidate->photo_path) {
                $this->cloudinary->delete($candidate->photo_path);
            }
            $data['photo_path'] = $this->cloudinary->upload($request->file('photo'));
        }

        $this->candidatService->update($candidate, $data);
        $this->forgetCacheByPrefix('candidates_list');
        $this->forgetCache("candidate_{$id}");
        $this->forgetCache('admin_global_stats');

        return response()->json(['message' => 'Candidat mis à jour avec succès']);
    }

    public function destroy($id)
    {
        $candidate = Candidate::findOrFail($id);

        if ($candidate->photo_path) {
            $this->cloudinary->delete($candidate->photo_path);
        }

        $result = $this->candidatService->delete($candidate);

        if ($result) {
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
        if (!$user) return response()->json(['message' => 'Non authentifié'], 401);

        $validator = Validator::make($request->all(), [
            'position_id' => 'required|exists:positions,id',
            'bio'         => 'nullable|string',
            'slogan'      => 'nullable|string',
            'photo'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $existing = Candidate::where('user_id', $user->id)->where('position_id', $request->position_id)->first();
        if ($existing) return response()->json(['message' => 'Vous avez déjà postulé à ce poste.'], 409);

        $data = $request->only('position_id', 'bio', 'slogan');
        $data['user_id'] = $user->id;
        $data['status']  = 'en_attente';

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $this->cloudinary->upload($request->file('photo'));
        }

        $candidate = Candidate::create($data);
        $this->forgetCacheByPrefix('candidates_list');
        $this->forgetCache('admin_global_stats');

        return response()->json([
            'message' => "Votre candidature a été enregistrée. Elle sera examinée par l'administration.",
            'data'    => $candidate,
        ], 201);
    }

    public function approve($id)
    {
        $candidate = Candidate::findOrFail($id);
        if ($candidate->status !== 'en_attente') {
            return response()->json(['message' => "Ce candidat n'est pas en attente."], 400);
        }
        $candidate->update(['status' => 'valide']);
        $this->forgetCacheByPrefix('candidates_list');
        $this->forgetCache("candidate_{$id}");
        $this->forgetCache('admin_global_stats');
        return response()->json(['message' => 'Candidature validée.']);
    }

    public function reject($id)
    {
        $candidate = Candidate::findOrFail($id);
        if ($candidate->status !== 'en_attente') {
            return response()->json(['message' => "Ce candidat n'est pas en attente."], 400);
        }
        $candidate->update(['status' => 'refuse']);
        $this->forgetCacheByPrefix('candidates_list');
        $this->forgetCache("candidate_{$id}");
        $this->forgetCache('admin_global_stats');
        return response()->json(['message' => 'Candidature refusée.']);
    }

    public function show($id): JsonResponse
    {
        $candidate = $this->rememberCache("candidate_{$id}", function () use ($id) {
            return Candidate::with('user', 'position')->findOrFail($id)->toArray();
        }, $this->cacheTtl);

        return response()->json(['success' => true, 'data' => $candidate]);
    }

    protected function forgetCacheByPrefix(string $prefix): void
    {
        $statuses  = ['all', 'en_attente', 'valide', 'refuse'];
        $positions = \App\Models\Position::pluck('id')->toArray();
        $positions[] = 'all';

        foreach ($positions as $posId) {
            foreach ($statuses as $status) {
                $this->forgetCache("candidates_list_pos_{$posId}_status_{$status}");
            }
        }
    }
}
