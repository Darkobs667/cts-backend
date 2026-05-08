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

Route::get('/check-users', function () {
    $users = DB::table('users')->select('email', 'first_name', 'last_name')->get();
    return response()->json($users);
});



/********** Routes de debug temporaire **********/
Route::get('/debug-storage', function () {
    $files = [];
    $storagePath = storage_path('app/public/candidates');
    
    if (file_exists($storagePath)) {
        $files = scandir($storagePath);
    }
    
    return response()->json([
        'storage_linked' => is_link(public_path('storage')),
        'files_exist' => file_exists($storagePath),
        'files' => $files,
        'public_path' => public_path(),
        'storage_path' => $storagePath
    ]);
});

/**********Route de surveillance du Corn_job **********/
Route::get('/cron-status', function () {
    $lastCalled = Cache::get('last_cron_call');
    
    return response()->json([
        'last_keep_alive' => $lastCalled,
        'is_awake' => $lastCalled && now()->diffInMinutes($lastCalled) < 15,
        'next_scheduled' => now()->addMinutes(10)
    ]);
});

/********** Routes de debug temporaire 2 **********/
Route::get('/check-storage', function () {
    $publicStorageExists = file_exists(public_path('storage'));
    $storageLinkExists = is_link(public_path('storage'));
    $candidateFiles = [];
    
    if (file_exists(storage_path('app/public/candidates'))) {
        $candidateFiles = array_diff(scandir(storage_path('app/public/candidates')), ['.', '..']);
    }
    
    return response()->json([
        'public_storage_exists' => $publicStorageExists,
        'storage_link_exists' => $storageLinkExists,
        'candidate_files' => $candidateFiles
    ]);
});
/********** Routes pour empecher le backend sur render de s'endormir apres 15 min d'inactivité **********/
Route::match(['GET', 'HEAD'], '/keep-alive', function () {
    return response('', 200);
}); // Max 10 requêtes par minute (sécurité)

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
