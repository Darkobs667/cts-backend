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
        // Vérifie si l'admin existe déjà
        $admin = DB::table('users')->where('email', 'admin@uadb.edu.sn')->first();
        
        if (!$admin) {
            DB::table('users')->insert([
                'first_name' => 'Admin',
                'last_name' => 'CTS',
                'email' => 'admin@uadb.edu.sn',
                'password' => Hash::make('admin@221'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        DB::table('users')->where('email', 'admin@uadb.edu.sn')->delete();
    }
};
