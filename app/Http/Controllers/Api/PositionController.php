<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Services\PositionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;


class PositionController extends Controller
{
    protected $positionService;

    public function __construct(PositionService $positionService)
    {
        $this->positionService = $positionService;
    }

    /**
     * Liste tous les postes (Accessible à tous les authentifiés)
     */
    public function index(): JsonResponse
{
    try {
        $positions = $this->positionService->getAll();
        
        // Vérifier si la réponse est valide
        if (!$positions) {
            $positions = collect([]);
        }
        
        return response()->json([
            'success' => true,
            'data' => $positions
        ]);
    } catch (\Exception $e) {
        // Log l'erreur pour debug
        \Log::error('Erreur dans PositionController@index: ' . $e->getMessage());
        
        // Retourner un tableau vide au lieu d'une erreur
        return response()->json([
            'success' => true,
            'data' => collect([]),
            'debug_message' => $e->getMessage() // Temporaire, à retirer ensuite
        ]);
    }
}

    /**
     * Créer un nouveau poste (Admin uniquement)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|unique:positions,title|max:255',
            'description' => 'nullable|string',
            'is_active'   => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $position = $this->positionService->create($request->all());
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

    $data = $request->only('title', 'description', 'is_active');

    // Si on active le scrutin et qu'il n'a pas encore de date de début, on l'enregistre
    if (isset($data['is_active']) && $data['is_active'] == true && !$position->started_at) {
        $data['started_at'] = now();
    }

    // Si on désactive, on efface started_at (pour un éventuel prochain démarrage)
    if (isset($data['is_active']) && $data['is_active'] == false) {
        $data['started_at'] = null;
    }

    $result = $this->positionService->update($position, $data);

    if ($result) {
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
        return response()->json(['message' => 'Poste supprimé avec succès']);
    }

    return response()->json(['message' => 'Erreur lors de la suppression'], 500);
}

    /**
     * Liste des postes actifs pour les électeurs
     */
    public function activePositions(): JsonResponse
    {
        $positions = $this->positionService->getActivePositions();
        return response()->json(['data' => $positions]);
    }
}