<?php

namespace App\Services;

use App\Models\Candidate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Vote;
use App\Services\CandidatePhotoService;

class CandidatService 
{
    public function __construct(private readonly CandidatePhotoService $photoService)
    {
    }
    /**
     * Créer un nouveau candidat (Réservé aux admins)
     */
    public function create(array $data): Candidate
    {
        $user = auth('api')->user();

        // Correction de la condition : on vérifie si l'utilisateur existe 
        // ET si son rôle est bien 'admin'
        if (!$user || $user->role !== 'admin') {
            throw new \Exception('Utilisateur non authentifié ou droits insuffisants', 403);
        }

        return Candidate::create([
            'user_id'     => $data['user_id'], 
            'position_id' => $data['position_id'],
            'bio'         => $data['bio'] ?? null,
            'slogan'      => $data['slogan'] ?? null,
            'status'      => $data['status'] ?? 'valide',
            'photo_path'  => $data['photo_path'] ?? null,
            'photo_public_id' => $data['photo_public_id'] ?? null,
        ]);
    }

    /**
     * Mettre à jour le profil d'un candidat
     */
    public function update(Candidate $candidate, array $data): bool
    {
        $user = auth('api')->user();

        if (!$user) {
            throw new \Exception('Non authentifié', 401);
        }

       
        if ($candidate->user_id !== $user->id && $user->role !== 'admin') {
            throw new \Exception('Action non autorisée', 403);
        }

        return $candidate->update($data);
    }

    /**
     * Récupérer le profil candidat de l'utilisateur connecté
     */
    public function getProfile(): ?Candidate
    {
        $user = auth('api')->user();
        
        if (!$user) {
            throw new \Exception('Non authentifié', 401);
        }

        return Candidate::with(['user', 'position'])
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Récupérer la liste des candidats
     */
    public function getAll(): Collection
    {
        $user = auth('api')->user();

        if (!$user) {
            throw new \Exception('Non authentifié', 401);
        }

        $query = Candidate::with(['user', 'position']);


        if ($user->role === 'admin') {
            return $query->get();
        }

        // Par défaut pour une élection, on retourne souvent tous les candidats 
        return $query->get();
    }

    /**
     * Supprimer un candidat
     */
 public function delete(Candidate $candidate): bool
{
    $user = auth('api')->user();
    if (!$user || $user->role !== 'admin') {
        throw new \Exception('Seul un administrateur peut supprimer un candidat.', 403);
    }

    // Supprimer les votes pour ce candidat
    Vote::where('candidate_id', $candidate->id)->delete();

    // Supprimer la photo si elle existe
    if ($candidate->photo_public_id) {
        $this->photoService->delete($candidate->photo_public_id);
    } elseif ($candidate->photo_path) {
        \Storage::disk('public')->delete($candidate->photo_path);
    }

    return $candidate->delete();
}


}
