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
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'code'       => 'nullable|string|unique:users,code',
            'email'      => 'required|string|email|max:255|unique:users,email',
            'password'   => 'required|string|min:8|confirmed',
            'browserId' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        

        try {
            $result = $this->authService->register($request->all());
            if(isset($result['errors'])){
                    return response()->json([
                        'error' => $result['errors'],
                    ],'401');
            }else{
            return response()->json([
                'message' => 'Utilisateur créé avec succès',
                'data' => $result,
                
            ], 201);
            }
        } catch (\Exception $e) {
            //return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
            // On garde 500 car 22000 n'est pas un code HTTP valide
            return response()->json(['error' => $e->getMessage()], 500);
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
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 401);
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
}