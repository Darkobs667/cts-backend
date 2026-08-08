<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthServices;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthServices $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'code'       => 'nullable|string|max:100|unique:users,code',
            'email'      => ['required', 'string', 'email:rfc', 'max:255', 'regex:/^[^@\\s]+@uadb\\.edu\\.sn$/i', 'unique:users,email'],
            'password'   => 'required|string|min:12|confirmed',
            'browserId' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        

        try {
            $result = $this->authService->register($validator->validated());
            if(isset($result['errors'])){
                    return response()->json([
                        'error' => $result['errors'],
                    ], 409);
            }else{
            return response()->json([
                'message' => 'Utilisateur créé avec succès',
                'data' => $result,
                
            ], 201);
            }
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Impossible de créer le compte pour le moment.'], 500);
        }
    }

    /**
     * Connexion de l'utilisateur
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        $validator = Validator::make($credentials, [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->authService->login($credentials);
            return response()->json([
                'message' => 'Connexion réussie',
                'data' => $result
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Identifiants invalides.'], 401);
        }
    }

    /**
     * Déconnexion (Invalider le token)
     */
    public function logout(): JsonResponse
    {
        $loggedOut = $this->authService->logout();

        if ($loggedOut) {
            return response()->json(['message' => 'Déconnexion réussie'], 200);
        }

        return response()->json(['error' => 'Erreur lors de la déconnexion'], 500);
    }

    /**
     * Obtenir les informations de l'utilisateur connecté
     */
    public function me(): JsonResponse
    {
        $user = $this->authService->me();

        if (!$user) {
            return response()->json(['error' => 'Non autorisé'], 401);
        }

        return response()->json(['user' => $user], 200);
    }

    /**
     * Rafraîchir le token d'accès
     */
    public function refresh(): JsonResponse
    {
        try {
            $tokens = $this->authService->refresh();
            return response()->json($tokens, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Impossible de rafraîchir le token'], 401);
        }
    }

    /**
     * Rafraîchissement via le Refresh Token spécifique
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $request->validate(['refresh_token' => 'required|string']);
        
        $user = $this->authService->validateRefreshToken($request->refresh_token);

        if (!$user) {
            return response()->json(['error' => 'Refresh token invalide ou expiré'], 401);
        }


        $accessToken = JWTAuth::fromUser($user);
        
        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'bearer',
        ]);
    }
     /**
     * Vérifier le rôle de l'utilisateur connecté
     * NOUVELLE MÉTHODE À AJOUTER
     */
    public function verifyRole(): JsonResponse
    {
        try {
            $user = $this->authService->me();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Non authentifié'
                ], 401);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'role' => $user->role,        // 'admin' ou 'elect'
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Token invalide ou expiré'
            ], 401);
        }
    }

    /**
     * Vérifier rapidement si l'utilisateur est admin
     * NOUVELLE MÉTHODE À AJOUTER
     */
    public function checkAdmin(): JsonResponse
    {
        try {
            $user = $this->authService->me();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'is_admin' => false
                ], 401);
            }
            
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
