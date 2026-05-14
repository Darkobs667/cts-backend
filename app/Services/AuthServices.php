<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\DB;

class AuthServices
{
   /**
     * Register a new user
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function register(array $data): array
    {
        // Validation email institutionnel
        if (!preg_match('/^[^\s@]+@uadb\.edu\.sn$/', $data['email'])) {
            return ['errors' => 'Seules les adresses @uadb.edu.sn sont autorisées.'];
        }

        // Vérification du code d'invitation
        $invite = \App\Models\InviteCode::where('code', $data['invite_code'] ?? '')
            ->where('used', false)
            ->first();
        if (!$invite) {
            return ['errors' => 'Code d\'invitation invalide ou déjà utilisé.'];
        }

        if (User::where('browserId', $data['browserId'])->exists()) {
            return ['errors' => 'Un compte existe déjà sur cet appareil.'];
        }

        if (User::where('email', $data['email'])->exists()) {
            return ['errors' => 'Cette adresse email est déjà utilisée.'];
        }

        DB::beginTransaction();

        try {
            $user = User::create([
                'first_name'  => $data['first_name'],
                'last_name'   => $data['last_name'],
                'code'        => $data['code'] ?? null,
                'email'       => $data['email'],
                'browserId'   => $data['browserId'],
                'invite_code' => $data['invite_code'],
                'password'    => Hash::make($data['password']),
            ]);

            // Marquer le code comme utilisé
            $invite->update(['used' => true, 'used_by' => $user->id]);

            $tokens = $this->generateTokens($user);

            DB::commit();

            return [
                'user' => $user,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


    public static function voterHash(User $user): string
    {
        return hash('sha256', $user->id . config('app.key'));
    }

    /**
     * Authenticate user and return tokens
     *
     * @param array $credentials
     * @return array|null
     * @throws \Exception
     */
    public function login(array $credentials): ?array
    {
        $user = null;

        if (isset($credentials['email'])) {
            $user = User::where('email', $credentials['email'])->first();
        } else {
            throw new \Exception("Email est requis pour la connexion", 422);
        }

        if (!$user) {
            throw new \Exception("Utilisateur non trouvé avec ce email ", 404);
        }

        if (!Hash::check($credentials['password'], $user->password)) {
            throw new \Exception("Mot de passe incorrect", 401);
        }

        $tokens = $this->generateTokens($user);

        return [
            'user' => $user->fresh(),
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
        ];
    }

    /**
     * Logout user and invalidate token
     *
     * @return bool
     */
    public function logout(): bool
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return true;
        } catch (JWTException $e) {
            return false;
        }
    }

    /**
     * Refresh access token
     *
     * @return array
     * @throws JWTException
     */
    public function refresh(): array
    {
        try {
            $user = auth('api')->user();
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
            $refreshToken = $this->generateRefreshToken($user);

            return [
                'access_token' => $newToken,
                'refresh_token' => $refreshToken,
            ];
        } catch (JWTException $e) {
            throw new JWTException('Could not refresh token');
        }
    }

    /**
     * Get authenticated user
     *
     * @return User|null
     */
    public function me(): ?User
    {
        try {
            return auth('api')->user();
        } catch (JWTException $e) {
            return null;
        }
    }

    /**
     * Generate access and refresh tokens for user
     *
     * @param User $user
     * @return array
     */
    private function generateTokens(User $user): array
    {
        $accessToken = JWTAuth::fromUser($user);
        $refreshToken = $this->generateRefreshToken($user);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }

    /**
     * Generate refresh token
     *
     * @param User $user
     * @return string
     */
    private function generateRefreshToken(User $user): string
    {
        // For simplicity, we'll use JWT with longer expiration for refresh token
        // In production, consider using a separate table for refresh tokens
        $customClaims = [
            'type' => 'refresh',
            'user_id' => $user->id,
            'exp' => now()->addDays(30)->timestamp, // 1 month
        ];

        return JWTAuth::customClaims($customClaims)->fromUser($user);
    }

    /**
     * Validate refresh token and return user
     *
     * @param string $refreshToken
     * @return User|null
     */
    public function validateRefreshToken(string $refreshToken): ?User
    {
        try {
            $payload = JWTAuth::setToken($refreshToken)->getPayload();

            if ($payload->get('type') !== 'refresh') {
                return null;
            }

            $userId = $payload->get('user_id');
            return User::find($userId);
        } catch (JWTException $e) {
            return null;
        }
    }
}
