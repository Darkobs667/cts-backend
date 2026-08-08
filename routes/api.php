<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\CandidateController;
use App\Http\Controllers\Api\VoteController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// routes/api.php - Ajoutez cette route en TOUT PREMIER
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'time' => now()->toIso8601String(),
        'memory' => memory_get_usage(),
        'database' => DB::connection()->getPdo() ? 'connected' : 'error'
    ]);
});

// =============================================
// ROUTES PUBLIQUES (Accessibles sans authentification)
// =============================================

// ROUTES pour avoir toutes les resultats (Accessibles sans authentification)
Route::get('/votes/results/all', [VoteController::class, 'allResults']);
Route::get('/votes/results/pdf', [VoteController::class, 'exportPDF']);

// Test
Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

// Authentification
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:refresh');

// Consultation publique
Route::get('/positions', [PositionController::class, 'index']);
Route::get('/candidates', [CandidateController::class, 'index']);
Route::get('/votes/results', [VoteController::class, 'results']);

// Keep-alive pour Render (évite la mise en veille)
Route::match(['GET', 'HEAD'], '/keep-alive', function () {
    return response('', 200);
});


Route::get('/keep-alive', function () {
    return response('OK', 200)
        ->header('Content-Type', 'text/plain')
        ->header('Cache-Control', 'no-cache');
});


// =============================================
// ROUTES PROTÉGÉES PAR JWT (Authentification requise)
// =============================================

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/auth/verify-role', [AuthController::class, 'verifyRole']);
    Route::get('/auth/check-admin', [AuthController::class, 'checkAdmin']);
    Route::get('/admin/stats-globales', [AdminController::class, 'getStats'])->middleware('admin');
    
    // Positions (admin uniquement pour écriture)
    Route::post('/positions', [PositionController::class, 'store'])->middleware(['admin', 'throttle:admin-write']);
    Route::put('/positions/{id}', [PositionController::class, 'update'])->middleware(['admin', 'throttle:admin-write']);
    Route::delete('/positions/{id}', [PositionController::class, 'destroy'])->middleware(['admin', 'throttle:admin-write']);
    Route::get('/positions/{id}', [PositionController::class, 'show']);
    
    // Candidats
    Route::post('/candidates', [CandidateController::class, 'store'])->middleware(['admin', 'throttle:upload']);
    Route::put('/candidates/{id}', [CandidateController::class, 'update'])->middleware(['admin', 'throttle:upload']);
    Route::delete('/candidates/{id}', [CandidateController::class, 'destroy'])->middleware(['admin', 'throttle:admin-write']);
    Route::get('/candidates/{id}', [CandidateController::class, 'show']);
    
    // Approbation/refus des candidatures (admin)
    Route::put('/candidates/{id}/approve', [CandidateController::class, 'approve'])->middleware(['admin', 'throttle:admin-write']);
    Route::put('/candidates/{id}/reject', [CandidateController::class, 'reject'])->middleware(['admin', 'throttle:admin-write']);
    
    // Postuler (utilisateur connecté)
    Route::post('/apply', [CandidateController::class, 'apply'])->middleware(['throttle:application', 'throttle:upload']);
    
    // Utilisateurs (admin)
    Route::get('/users', [UserController::class, 'index'])->middleware('admin');
    Route::put('/users/{id}/reset-password', [UserController::class, 'resetPassword'])->middleware('admin');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('admin');
    
    // Votes
    Route::post('/votes', [VoteController::class, 'store'])->middleware('throttle:vote');
    Route::post('/votes/batch', [VoteController::class, 'batchStore'])->middleware('throttle:vote');
    Route::get('/votes/my', [VoteController::class, 'myVotes']);
    Route::get('/votes/check/{positionId}', [VoteController::class, 'checkVote']);
    Route::get('/voter/receipt/{voteId}', [VoteController::class, 'receipt']);
    
});
