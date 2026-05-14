<?php

namespace App\Services;

use App\Models\User;
use App\Mail\VerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
        if (!preg_match('/^[^\s@]+@uadb\.edu\.sn$/', $data['email'])) {
            return ['errors' => 'Seules les adresses @uadb.edu.sn sont autorisées.'];
        }

        if (User::where('browserId', $data['browserId'])->exists()) {
            return ['errors' => 'Un compte existe déjà sur cet appareil.'];
        }

        if (User::where('email', $data['email'])->exists()) {
            return ['errors' => 'Cette adresse email est déjà utilisée.'];
        }

        DB::beginTransaction();

        try {
            $token = Str::random(64);

            $user = User::create([
                'first_name'               => $data['first_name'],
                'last_name'                => $data['last_name'],
                'code'                     => $data['code'] ?? null,
                'email'                    => $data['email'],
                'browserId'                => $data['browserId'],
                'invite_code'              => null,
                'password'                 => Hash::make($data['password']),
                'email_verification_token' => $token,
            ]);

            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
            Mail::to($user->email)->send(new VerifyEmail($token, $frontendUrl));

            DB::commit();

            return ['email_sent' => true];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function verifyEmail(string $token): bool
    {
        $user = User::where('email_verification_token', $token)
            ->whereNull('email_verified_at')
            ->first();

        if (!$user) return false;

        $user->update([
            'email_verified_at'        => now(),
            'email_verification_token' => null,
        ]);

        return true;
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

        if (!$user->email_verified_at) {
            throw new \Exception("Veuillez vérifier votre email avant de vous connecter.", 403);
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
