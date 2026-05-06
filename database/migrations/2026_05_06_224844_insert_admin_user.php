<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Vérifier si la table users existe (par sécurité)
        if (!Schema::hasTable('users')) {
            return;
        }

        DB::table('users')->updateOrInsert(
            ['email' => 'admin@uadb.edu.sn'],
            [
                'name' => 'Admin',
                'password' => Hash::make('admin@221'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down()
    {
        DB::table('users')->where('email', 'admin@uadb.edu.sn')->delete();
    }
};
