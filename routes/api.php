<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\CandidateController;
use App\Http\Controllers\Api\VoteController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\InviteCodeController;
use App\Http\Controllers\Api\PhysicalVoteController;

// =============================================
// ROUTES PUBLIQUES
// =============================================

Route::get('/ping', fn() => response()->json(['message' => 'pong']));

Route::match(['GET', 'HEAD'], '/keep-alive', fn() => response('', 200));

// Auth — rate limited
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

Route::get('/verify-email/{token}', [AuthController::class, 'verifyEmail']);

Route::post('/refresh',       [AuthController::class, 'refresh']);
Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

// Consultation publique
Route::get('/positions',  [PositionController::class, 'index']);
Route::get('/candidates', [CandidateController::class, 'index']);
Route::get('/votes/results', [VoteController::class, 'results']);

// =============================================
// ROUTES PROTÉGÉES PAR JWT
// =============================================

Route::middleware('auth:api')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // Stats admin — protégées
    Route::get('/admin/stats-globales', [AdminController::class, 'getStats'])->middleware('admin');

    // Résultats complets — admin uniquement
    Route::get('/votes/results/all', [VoteController::class, 'allResults'])->middleware('admin');
    Route::get('/votes/results/pdf', [VoteController::class, 'exportPDF'])->middleware('admin');

    // Positions
    Route::post('/positions',        [PositionController::class, 'store'])->middleware('admin');
    Route::put('/positions/{id}',    [PositionController::class, 'update'])->middleware('admin');
    Route::delete('/positions/{id}', [PositionController::class, 'destroy'])->middleware('admin');
    Route::get('/positions/{id}',    [PositionController::class, 'show']);

    // Candidats
    Route::post('/candidates',        [CandidateController::class, 'store'])->middleware('admin');
    Route::put('/candidates/{id}',    [CandidateController::class, 'update'])->middleware('admin');
    Route::delete('/candidates/{id}', [CandidateController::class, 'destroy'])->middleware('admin');
    Route::get('/candidates/{id}',    [CandidateController::class, 'show']);

    Route::put('/candidates/{id}/approve', [CandidateController::class, 'approve'])->middleware('admin');
    Route::put('/candidates/{id}/reject',  [CandidateController::class, 'reject'])->middleware('admin');

    Route::post('/apply', [CandidateController::class, 'apply']);

    // Utilisateurs
    Route::get('/users',                        [UserController::class, 'index'])->middleware('admin');
    Route::put('/users/{id}/reset-password',    [UserController::class, 'resetPassword'])->middleware('admin');
    Route::delete('/users/{id}',                [UserController::class, 'destroy'])->middleware('admin');

    // Votes
    Route::post('/votes',                  [VoteController::class, 'store']);
    Route::get('/votes/my',                [VoteController::class, 'myVotes']);
    Route::get('/voter/receipt/{voteId}',  [VoteController::class, 'receipt']);

    // Codes d'invitation (admin)
    Route::post('/invite-codes/generate',  [InviteCodeController::class, 'generate'])->middleware('admin');
    Route::get('/invite-codes',            [InviteCodeController::class, 'index'])->middleware('admin');
    Route::delete('/invite-codes/{id}',    [InviteCodeController::class, 'destroy'])->middleware('admin');

    // Votes physiques jour J (admin)
    Route::post('/positions/{positionId}/physical-votes', [PhysicalVoteController::class, 'store'])->middleware('admin');
});
