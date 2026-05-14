<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Services\PositionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Traits\Cacheable;


class PositionController extends Controller
{
    use Cacheable;  // ← utilisation du cache
    protected $positionService;
    protected $cacheTtl = 300;  // ← (5 minutes)

    public function __construct(PositionService $positionService)
    {
        $this->positionService = $positionService;
    }

    /**
     * Liste tous les postes (Accessible à tous les authentifiés)
     */
    public function index(): JsonResponse
    {
        // Clôturer les scrutins expirés avant de retourner la liste
        $closed = Position::where('is_active', true)
            ->whereNotNull('closes_at')
            ->where('closes_at', '<=', now())
            ->update(['is_active' => false]);

        if ($closed > 0) {
            $this->forgetCache('positions_list');
            $this->forgetCache('vote_results_all');
            $this->forgetCache('admin_global_stats');
        }

        $positions = $this->rememberCache('positions_list', function () {
            $positions = $this->positionService->getAll();
            
            // Convertir les objets en array pour éviter les problèmes de sérialisation
            return json_decode(json_encode($positions), true);
        }, $this->cacheTtl);

        return response()->json([
            'success' => true,
            'data' => $positions
        ]);
    }

    public function show($id): JsonResponse
    {
        $position = $this->rememberCache("position_{$id}", function () use ($id) {
            return Position::findOrFail($id)->toArray();
        }, $this->cacheTtl);

        return response()->json([
            'success' => true,
            'data'    => $position,
        ]);
    }

    /**
     * Créer un nouveau poste (Admin uniquement)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|unique:positions,title|max:255',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
            'closes_at'   => 'nullable|date|after:now',
            'quorum'      => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $position = $this->positionService->create($request->all());
            
            // ← VIDER LE CACHE APRÈS CRÉATION
            $this->forgetCache('positions_list');
            
            return response()->json([
                'message' => 'Poste créé avec succès',
                'data' => $position
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Mettre à jour un poste (Admin uniquement)
     */
    public function update(Request $request, $id)
    {
        $position = Position::findOrFail($id);

        $data = $request->only('title', 'description', 'is_active', 'closes_at', 'quorum');

        if (isset($data['is_active']) && $data['is_active'] == true && !$position->started_at) {
            $data['started_at'] = now();
        }

        if (isset($data['is_active']) && $data['is_active'] == false) {
            $data['started_at'] = null;
        }

        $result = $this->positionService->update($position, $data);

        if ($result) {
            // ← VIDER LE CACHE APRÈS MODIFICATION
            $this->forgetCache('positions_list');
            $this->forgetCache("position_{$id}");
            
            // ← AJOUT : VIDER LES CACHES DES RÉSULTATS ET STATS
            $this->forgetCache('vote_results_all');
            $this->forgetCache('vote_results_' . $id);
            $this->forgetCache('admin_global_stats');
            
            return response()->json([
                'message' => 'Poste mis à jour avec succès',
                'data' => $position->fresh()
            ]);
        }
        return response()->json(['message' => 'Erreur lors de la mise à jour'], 500);
    }

    /**
     * Activer ou désactiver un poste (Admin uniquement)
     */
    public function toggle(Position $position): JsonResponse
    {
        try {
            $this->positionService->toggleStatus($position);
            $status = $position->is_active ? 'activé' : 'désactivé';
            
            // ← VIDER LE CACHE APRÈS ACTIVATION/DÉSACTIVATION
            $this->forgetCache('positions_list');
            $this->forgetCache("position_{$position->id}");
            
            // ← AJOUT : VIDER LES CACHES DES RÉSULTATS ET STATS
            $this->forgetCache('vote_results_all');
            $this->forgetCache('vote_results_' . $position->id);
            $this->forgetCache('admin_global_stats');
            
            return response()->json(['message' => "Le poste est désormais $status"]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Supprimer un poste (Admin uniquement)
     */
    public function destroy($id)
    {
        $position = Position::findOrFail($id);
        $result = $this->positionService->delete($position);

        if ($result) {
            // ← VIDER LE CACHE APRÈS SUPPRESSION
            $this->forgetCache('positions_list');
            $this->forgetCache("position_{$id}");
            
            // ← AJOUT : VIDER LES CACHES DES RÉSULTATS ET STATS
            $this->forgetCache('vote_results_all');
            $this->forgetCache('vote_results_' . $id);
            $this->forgetCache('admin_global_stats');
            
            return response()->json(['message' => 'Poste supprimé avec succès']);
        }

        return response()->json(['message' => 'Erreur lors de la suppression'], 500);
    }

    /**
     * Liste des postes actifs pour les électeurs
     */
    public function activePositions(): JsonResponse
    {
        $positions = $this->rememberCache('positions_active_list', function () {
            return $this->positionService->getActivePositions();
        }, $this->cacheTtl);

        return response()->json(['data' => $positions]);
    }
}