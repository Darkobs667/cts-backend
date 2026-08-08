<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Les comptes administrateur sont créés par une procédure sécurisée,
        // jamais avec des identifiants connus dans le code source.
    }

    public function down(): void
    {
        // Aucune donnée applicative à annuler.
    }
};
