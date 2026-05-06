<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});



use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\CandidateController;
use App\Http\Controllers\Api\VoteController;
use App\Http\Controllers\Api\UserController;



/********** Routes d'authentification **********/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

Route::get('/setup-admin', function () {
    // Vérifier si l'utilisateur existe déjà
    $user = \App\Models\User::where('email', 'admin@uadb.edu.sn')->first();
    if ($user) {
        return response()->json(['message' => 'Admin déjà existant', 'user' => $user]);
    }

    $user = \App\Models\User::create([
        'name' => 'Admin',
        'email' => 'admin@uadb.edu.sn',
        'password' => bcrypt('admin@221'),
        'email_verified_at' => now(),
    ]);

    return response()->json(['message' => 'Admin créé avec succès !', 'user' => $user]);
});


  // Nouvelle route pour les statistiques :
   Route::get('/admin/stats-globales', [App\Http\Controllers\Api\AdminController::class, 'getStats']);

   Route::get('/votes/my', [VoteController::class, 'myVotes']);

   Route::get('/voter/receipt/{voteId}', [VoteController::class, 'receipt']);

/********** Routes protégées par JWT **********/
Route::middleware('auth:api')->group(function () {
    // Positions
    Route::get('/positions', [PositionController::class, 'index']);
    Route::get('/users', [UserController::class, 'index'])->middleware('admin');
    Route::post('/positions', [PositionController::class, 'store'])->middleware('admin');
    Route::put('/positions/{id}', [PositionController::class, 'update'])->middleware('admin');
    Route::delete('/positions/{id}', [PositionController::class, 'destroy'])->middleware('admin');
    Route::get('/positions/{id}', [PositionController::class, 'show']);

    //ACCEPTER OU REFUSER UNE candidature
    Route::put('/candidates/{id}/approve', [CandidateController::class, 'approve'])->middleware('admin');
Route::put('/candidates/{id}/reject', [CandidateController::class, 'reject'])->middleware('admin');
Route::post('/apply', [CandidateController::class, 'apply']);

//reinitialisation de mot de passe d'un utilisateur  par admin
Route::put('/users/{id}/reset-password', [UserController::class, 'resetPassword'])->middleware('admin');

//pour supprimer un utilisateur 
Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('admin');
    
    // Candidats
    Route::get('/candidates', [CandidateController::class, 'index']);
    Route::post('/candidates', [CandidateController::class, 'store'])->middleware('admin');
    Route::put('/candidates/{id}', [CandidateController::class, 'update'])->middleware('admin');
    Route::delete('/candidates/{id}', [CandidateController::class, 'destroy'])->middleware('admin');
    Route::get('/candidates/{id}', [CandidateController::class, 'show']);

    // Votes
    Route::post('/votes', [VoteController::class, 'store']);
    Route::get('/votes/results', [VoteController::class, 'results']);

  
});
