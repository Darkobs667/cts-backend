<?php
// app/Http/Controllers/Auth/RoleVerificationController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class RoleVerificationController extends Controller
{
    public function verifyRole(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'role' => $user->role,
                    'id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'is_admin' => $user->role === 'admin'
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Token invalide ou expiré'
            ], 401);
        }
    }
    
    // Endpoint spécifique pour vérifier si l'utilisateur est admin
    public function checkAdmin(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            return response()->json([
                'success' => true,
                'is_admin' => $user->role === 'admin'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'is_admin' => false
            ], 401);
        }
    }
}