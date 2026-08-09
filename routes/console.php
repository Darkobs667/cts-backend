<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cts:create-admin', function () {
    $email = strtolower(trim($this->ask('Adresse e-mail de l’administrateur')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Adresse e-mail invalide.');
        return self::FAILURE;
    }

    $existingUser = User::where('email', $email)->first();
    if ($existingUser && !$this->confirm("Le compte {$email} existe. Le promouvoir administrateur ?", true)) {
        $this->warn('Aucune modification effectuée.');
        return self::SUCCESS;
    }

    $firstName = trim($this->ask('Prénom', $existingUser?->first_name ?: 'Admin'));
    $lastName = trim($this->ask('Nom', $existingUser?->last_name ?: 'CTS'));
    $password = $this->secret('Mot de passe (12 caractères minimum)');
    $confirmation = $this->secret('Confirmer le mot de passe');

    if (mb_strlen($password) < 12) {
        $this->error('Le mot de passe doit contenir au moins 12 caractères.');
        return self::FAILURE;
    }

    if (!hash_equals($password, $confirmation)) {
        $this->error('Les mots de passe ne correspondent pas.');
        return self::FAILURE;
    }

    $user = $existingUser ?: new User();
    $user->first_name = $firstName;
    $user->last_name = $lastName;
    $user->email = $email;
    $user->password = Hash::make($password);
    $user->role = 'admin';
    $user->status = 'Validé';
    $user->email_verified_at ??= now();
    $user->save();

    $this->info("Le compte {$email} peut désormais administrer les élections.");
    return self::SUCCESS;
})->purpose('Créer ou promouvoir de façon sécurisée un compte administrateur CTS');

Artisan::command('cts:provision-admin', function () {
    $email = strtolower(trim((string) config('admin.initial_email')));
    $password = (string) config('admin.initial_password');

    // Cette commande est sûre à lancer à chaque démarrage : sans les deux
    // variables Render, elle ne modifie aucun compte et le déploiement continue.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 12) {
        $this->comment('Provisionnement administrateur ignoré : CTS_ADMIN_EMAIL ou CTS_ADMIN_PASSWORD absent/invalide.');
        return self::SUCCESS;
    }

    $user = User::firstOrNew(['email' => $email]);
    $created = !$user->exists;
    $user->first_name = $user->first_name ?: 'Admin';
    $user->last_name = $user->last_name ?: 'CTS';
    $user->password = Hash::make($password);
    $user->role = 'admin';
    $user->status = 'Validé';
    $user->email_verified_at ??= now();
    $user->save();

    $this->info($created
        ? "Compte administrateur {$email} créé."
        : "Compte {$email} promu ou mis à jour comme administrateur.");

    return self::SUCCESS;
})->purpose('Provisionner depuis CTS_ADMIN_EMAIL et CTS_ADMIN_PASSWORD le premier administrateur');
