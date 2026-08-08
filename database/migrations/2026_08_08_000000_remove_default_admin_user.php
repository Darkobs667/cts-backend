<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ce compte était créé automatiquement par une ancienne migration.
        // Un administrateur doit désormais être créé par une procédure dédiée.
        DB::table('users')->where('email', 'admin@uadb.edu.sn')->delete();
    }

    public function down(): void
    {
        // Ne jamais recréer un compte avec des identifiants connus.
    }
};
