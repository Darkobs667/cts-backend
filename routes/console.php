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
