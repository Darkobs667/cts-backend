<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\CandidateController;
use App\Http\Controllers\Api\VoteController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PhysicalVoteController;

// =============================================
// ROUTES PUBLIQUES
// =============================================

Route::get('/ping', fn() => response()->json(['message' => 'pong']));
Route::match(['GET', 'HEAD'], '/keep-alive', fn() => response('', 200));

// Route pour cron externe (cron-job.org) — clôture automatique des scrutins expirés
Route::get('/cron/close-expired', function () {
    $count = \App\Models\Position::where('is_active', true)
        ->whereNotNull('closes_at')
        ->where('closes_at', '<=', now())
        ->update(['is_active' => false]);
    return response()->json(['closed' => $count]);
});

Route::middleware('throttle:register')->post('/register', [AuthController::class, 'register']);
Route::middleware('throttle:login')->post('/login',    [AuthController::class, 'login']);

Route::post('/refresh',       [AuthController::class, 'refresh']);
Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

Route::get('/positions',     [PositionController::class, 'index']);
Route::get('/candidates',    [CandidateController::class, 'index']);
Route::get('/votes/results', [VoteController::class, 'results']);

// =============================================
// ROUTES PROTÉGÉES PAR JWT
// =============================================

Route::middleware('auth:api')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    Route::get('/admin/stats-globales', [AdminController::class, 'getStats'])->middleware('admin');

    Route::get('/votes/results/all', [VoteController::class, 'allResults'])->middleware('admin');
    Route::get('/votes/results/pdf', [VoteController::class, 'exportPDF'])->middleware('admin');

    Route::post('/positions',        [PositionController::class, 'store'])->middleware('admin');
    Route::put('/positions/{id}',    [PositionController::class, 'update'])->middleware('admin');
    Route::delete('/positions/{id}', [PositionController::class, 'destroy'])->middleware('admin');
    Route::get('/positions/{id}',    [PositionController::class, 'show']);

    Route::post('/candidates',             [CandidateController::class, 'store'])->middleware('admin');
    Route::post('/candidates/{id}/update',  [CandidateController::class, 'update'])->middleware('admin');
    Route::put('/candidates/{id}',          [CandidateController::class, 'update'])->middleware('admin');
    Route::delete('/candidates/{id}',      [CandidateController::class, 'destroy'])->middleware('admin');
    Route::get('/candidates/{id}',         [CandidateController::class, 'show']);
    Route::put('/candidates/{id}/approve', [CandidateController::class, 'approve'])->middleware('admin');
    Route::put('/candidates/{id}/reject',  [CandidateController::class, 'reject'])->middleware('admin');

    Route::get('/users',                     [UserController::class, 'index'])->middleware('admin');
    Route::put('/users/{id}/reset-password', [UserController::class, 'resetPassword'])->middleware('admin');
    Route::delete('/users/{id}',             [UserController::class, 'destroy'])->middleware('admin');

    Route::post('/votes',                 [VoteController::class, 'store']);
    Route::get('/votes/my',               [VoteController::class, 'myVotes']);
    Route::get('/voter/receipt/{voteId}', [VoteController::class, 'receipt']);

    Route::post('/positions/{positionId}/physical-votes', [PhysicalVoteController::class, 'store'])->middleware('admin');

});
