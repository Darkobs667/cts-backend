<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\DB;

class AuthServices
{
    public function register(array $data): array
    {
        if (User::where('email', $data['email'])->exists()) {
            return ['errors' => 'Cette adresse email est déjà utilisée.'];
        }

        $allowedDomain = env('ALLOWED_EMAIL_DOMAIN', null);
        if ($allowedDomain) {
            $emailDomain = substr(strrchr($data['email'], '@'), 1);
            if ($emailDomain !== $allowedDomain) {
                return ['errors' => "Seules les adresses @{$allowedDomain} sont autorisées."];
            }
        }

        DB::beginTransaction();
        try {
            User::create([
                'first_name'        => $data['first_name'],
                'last_name'         => $data['last_name'],
                'code'              => $data['code'] ?? null,
                'email'             => $data['email'],
                'browserId'         => $data['browserId'] ?? null,
                'password'          => Hash::make($data['password']),
                'role'              => $data['role'] ?? 'electeur',
                'email_verified_at' => now(),
            ]);
            DB::commit();
            return ['success' => true];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function login(array $credentials): ?array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            throw new \Exception("Aucun compte trouvé avec cet email.", 404);
        }

        if (!Hash::check($credentials['password'], $user->password)) {
            throw new \Exception("Mot de passe incorrect.", 401);
        }

        if ($user->status === 'suspendu' || $user->status === 'bloque') {
            throw new \Exception("Ce compte a été suspendu. Contactez un administrateur.", 403);
        }

        $tokens = $this->generateTokens($user);

        return [
            'user'          => $user->fresh(),
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
        ];
    }

    public function logout(): bool
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return true;
        } catch (JWTException $e) {
            return false;
        }
    }

    public function refresh(): array
    {
        try {
            $user = auth('api')->user();
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
            return [
                'access_token'  => $newToken,
                'refresh_token' => $this->generateRefreshToken($user),
            ];
        } catch (JWTException $e) {
            throw new JWTException('Could not refresh token');
        }
    }

    public function me(): ?User
    {
        try {
            return auth('api')->user();
        } catch (JWTException $e) {
            return null;
        }
    }

    public static function voterHash(User $user): string
    {
        return hash('sha256', $user->id . config('app.key'));
    }

    private function generateTokens(User $user): array
    {
        return [
            'access_token'  => JWTAuth::fromUser($user),
            'refresh_token' => $this->generateRefreshToken($user),
        ];
    }

    private function generateRefreshToken(User $user): string
    {
        return JWTAuth::customClaims([
            'type'    => 'refresh',
            'user_id' => $user->id,
            'exp'     => now()->addDays(30)->timestamp,
        ])->fromUser($user);
    }

    public function validateRefreshToken(string $refreshToken): ?User
    {
        try {
            $payload = JWTAuth::setToken($refreshToken)->getPayload();
            if ($payload->get('type') !== 'refresh') return null;
            return User::find($payload->get('user_id'));
        } catch (JWTException $e) {
            return null;
        }
    }
}
