<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
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

Route::get('/refresh-all-cache', function () {
    Cache::flush();
    return response()->json(['message' => 'Cache vidé']);
});

// Authentification
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

// Consultation publique
Route::get('/positions', [PositionController::class, 'index']);
Route::get('/candidates', [CandidateController::class, 'index']);
Route::get('/admin/stats-globales', [AdminController::class, 'getStats']);
Route::get('/votes/results', [VoteController::class, 'results']);

// Keep-alive pour Render (évite la mise en veille)
Route::match(['GET', 'HEAD'], '/keep-alive', function () {
    return response('', 200);
});

// Routes de debug (à retirer en production)
Route::get('/check-users', function () {
    $users = DB::table('users')->select('email', 'first_name', 'last_name')->get();
    return response()->json($users);
});

Route::get('/debug-positions', function () {
    try {
        $positions = \App\Models\Position::with('candidates')->get();
        return response()->json([
            'success' => true,
            'count' => $positions->count(),
            'data' => $positions
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

Route::get('/debug-storage', function () {
    $storagePath = storage_path('app/public/candidates');
    return response()->json([
        'storage_linked' => is_link(public_path('storage')),
        'files_exist' => file_exists($storagePath),
        'files' => file_exists($storagePath) ? scandir($storagePath) : [],
        'storage_path' => $storagePath
    ]);
});

Route::get('/check-storage', function () {
    $candidateFiles = [];
    if (file_exists(storage_path('app/public/candidates'))) {
        $candidateFiles = array_diff(scandir(storage_path('app/public/candidates')), ['.', '..']);
    }
    return response()->json([
        'public_storage_exists' => file_exists(public_path('storage')),
        'storage_link_exists' => is_link(public_path('storage')),
        'candidate_files' => $candidateFiles
    ]);
});

Route::get('/cron-status', function () {
    $lastCalled = Cache::get('last_cron_call');
    return response()->json([
        'last_keep_alive' => $lastCalled,
        'is_awake' => $lastCalled && now()->diffInMinutes($lastCalled) < 15,
        'next_scheduled' => now()->addMinutes(10)
    ]);
});

// =============================================
// ROUTES PROTÉGÉES PAR JWT (Authentification requise)
// =============================================

Route::middleware('auth:api')->group(function () {
    
    // Positions (admin uniquement pour écriture)
    Route::post('/positions', [PositionController::class, 'store'])->middleware('admin');
    Route::put('/positions/{id}', [PositionController::class, 'update'])->middleware('admin');
    Route::delete('/positions/{id}', [PositionController::class, 'destroy'])->middleware('admin');
    Route::get('/positions/{id}', [PositionController::class, 'show']);
    
    // Candidats
    Route::post('/candidates', [CandidateController::class, 'store'])->middleware('admin');
    Route::put('/candidates/{id}', [CandidateController::class, 'update'])->middleware('admin');
    Route::delete('/candidates/{id}', [CandidateController::class, 'destroy'])->middleware('admin');
    Route::get('/candidates/{id}', [CandidateController::class, 'show']);
    
    // Approbation/refus des candidatures (admin)
    Route::put('/candidates/{id}/approve', [CandidateController::class, 'approve'])->middleware('admin');
    Route::put('/candidates/{id}/reject', [CandidateController::class, 'reject'])->middleware('admin');
    
    // Postuler (utilisateur connecté)
    Route::post('/apply', [CandidateController::class, 'apply']);
    
    // Utilisateurs (admin)
    Route::get('/users', [UserController::class, 'index'])->middleware('admin');
    Route::put('/users/{id}/reset-password', [UserController::class, 'resetPassword'])->middleware('admin');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('admin');
    
    // Votes
    Route::post('/votes', [VoteController::class, 'store']);
    Route::get('/votes/my', [VoteController::class, 'myVotes']);
    Route::get('/voter/receipt/{voteId}', [VoteController::class, 'receipt']);
    
});