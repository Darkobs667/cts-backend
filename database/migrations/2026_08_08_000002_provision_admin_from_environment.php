<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $email = strtolower(trim((string) config('admin.initial_email')));
        $password = (string) config('admin.initial_password');

        // Ne crée rien sans secrets configurés dans l'environnement de déploiement.
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 12) {
            return;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->first_name = $user->first_name ?: 'Admin';
        $user->last_name = $user->last_name ?: 'CTS';
        $user->password = Hash::make($password);
        $user->role = 'admin';
        $user->status = 'Validé';
        $user->email_verified_at ??= now();
        $user->save();
    }

    public function down(): void
    {
        // Ne jamais supprimer automatiquement un compte administrateur.
    }
};
