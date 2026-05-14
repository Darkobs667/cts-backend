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

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users,email',
            'password'   => 'required|string|min:8|confirmed',
            'browserId'  => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->authService->register($request->all());
            if (isset($result['errors'])) {
                return response()->json(['error' => $result['errors']], 409);
            }
            return response()->json(['message' => 'Compte créé avec succès.'], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->only('email', 'password'), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->authService->login($request->only('email', 'password'));
            return response()->json(['message' => 'Connexion réussie', 'data' => $result], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 401);
        }
    }

    public function logout(): JsonResponse
    {
        return $this->authService->logout()
            ? response()->json(['message' => 'Déconnexion réussie'], 200)
            : response()->json(['error' => 'Erreur lors de la déconnexion'], 500);
    }

    public function me(): JsonResponse
    {
        $user = $this->authService->me();
        return $user
            ? response()->json(['user' => $user], 200)
            : response()->json(['error' => 'Non autorisé'], 401);
    }

    public function refresh(): JsonResponse
    {
        try {
            return response()->json($this->authService->refresh(), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Impossible de rafraîchir le token'], 401);
        }
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $request->validate(['refresh_token' => 'required|string']);
        $user = $this->authService->validateRefreshToken($request->refresh_token);

        if (!$user) {
            return response()->json(['error' => 'Refresh token invalide ou expiré'], 401);
        }

        return response()->json([
            'access_token' => JWTAuth::fromUser($user),
            'token_type'   => 'bearer',
        ]);
    }
}
