<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\Candidate;
use App\Models\Vote;


class UserController extends Controller
{
    public function index()
    {
        $users = User::select('id', 'first_name', 'last_name', 'email', 'role', 'status')
                     ->get()
                     ->map(function ($user) {
                         return [
                             'id'     => $user->id,
                             'nom'    => trim($user->first_name . ' ' . $user->last_name),
                             'email'  => $user->email,
                             'role'   => $user->role,
                             'status' => $user->status ?? 'Validé', // colonne inexistante → null → 'Validé'
                         ];
                     });

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    }

     /**
     * Réinitialiser le mot de passe d'un utilisateur (admin seulement)
     */
    public function resetPassword(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $newPassword = Str::random(12); // 12 caractères aléatoires
        $user->password = Hash::make($newPassword);
        $user->save();

        return response()->json([
            'message'      => 'Mot de passe réinitialisé avec succès.',
            'new_password' => $newPassword,
        ]);
    }

   public function destroy($id)
{
    $user = User::findOrFail($id);

    // Supprimer les candidatures de l'utilisateur
    Candidate::where('user_id', $user->id)->delete();

    // Supprimer les votes liés (via son email utilisé comme hash_session)
    Vote::where('hash_session', $user->email)->delete();

    // Supprimer l'utilisateur
    $user->delete();

    return response()->json(['message' => 'Utilisateur supprimé avec succès']);
}
}
