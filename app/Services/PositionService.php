<?php

namespace App\Services;

use App\Models\Position;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class PositionService 
{
    /**
     * Créer un nouveau poste (Réservé à l'admin)
     */
    public function create(array $data): Position
    {
        $user = auth('api')->user();

        // Vérification des droits admin basée sur l'énumération de la table users
        if (!$user || $user->role !== 'admin') {
            throw new \Exception('Seul un administrateur peut créer un poste.', 403);
        }

        return Position::create([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'is_active'   => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Récupérer tous les postes (avec leurs candidats)
     */
    public function getAll(): Collection
    {
        // On charge les candidats et les informations utilisateur liées pour l'affichage
        return Position::with(['candidates.user'])->get();
    }

    /**
     * Mettre à jour un poste (Titre, description ou activation)
     */
    public function update(Position $position, array $data): bool
{
    $user = auth('api')->user();
    if (!$user || $user->role !== 'admin') {
        throw new \Exception('Action réservée aux administrateurs.', 403);
    }

    return $position->update($data); // retourne un booléen
}

    /**
     * Basculer l'état d'un poste (Activer/Désactiver les votes)
     */
  public function toggleStatus(Position $position): void
{
    $user = auth('api')->user();
    if (!$user || $user->role !== 'admin') {
        throw new \Exception('Action réservée aux administrateurs.', 403);
    }

    $position->is_active = !$position->is_active;

    if ($position->is_active) {
        // Si on active le scrutin et qu'il n'a jamais été démarré, on enregistre la date actuelle
        if (!$position->started_at) {
            $position->started_at = now();
        }
    } else {
        // Si on désactive, on efface started_at pour un éventuel prochain démarrage
        $position->started_at = null;
    }

    $position->save();
}

    /**
     * Supprimer un poste
     * Note: La suppression entraînera celle des candidats et votes associés (onDelete cascade)
     */
   public function delete(Position $position): bool
    {
    $user = auth('api')->user();
    if (!$user || $user->role !== 'admin') {
        throw new \Exception('Seul un administrateur peut supprimer un poste.', 403);
    }

    // Supprime d'abord les candidats (évite l'erreur de clé étrangère)
    $position->candidates()->delete();

    return $position->delete(); // bool
    }

    /**
     * Récupérer les postes qui sont actuellement ouverts aux votes
     */
    public function getActivePositions(): Collection
    {
        return Position::where('is_active', true)->get();
    }
}