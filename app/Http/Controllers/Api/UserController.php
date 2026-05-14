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
use App\Services\AuthServices;


class UserController extends Controller
{
    public function index()
    {
        $users = User::select('id', 'first_name', 'last_name', 'email', 'role')
                     ->get()
                     ->map(function ($user) {
                         return [
                             'id'     => $user->id,
                             'nom'    => trim($user->first_name . ' ' . $user->last_name),
                             'email'  => $user->email,
                             'role'   => $user->role,
                             'status' => $user->status ?? 'Validé',
                         ];
                     });

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    }

    public function resetPassword(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $newPassword = Str::random(12);
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

        Candidate::where('user_id', $user->id)->delete();
        Vote::where('hash_session', AuthServices::voterHash($user))->delete();
        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé avec succès']);
    }
}
