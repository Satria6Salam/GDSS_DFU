<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ConsensusController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaderPanelController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\VoteController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

/*
| Daftar route mengikuti dokumen spesifikasi Bab 11.3.
| Semua route kecuali login berada dalam middleware auth; route ketua memakai middleware leader.
| Syarat status sesi diperiksa di SessionWorkflowService (Bab 7.5), bukan di route.
*/

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Penilaian milik pengguna yang login (H06–H08, A1–A4).
    Route::prefix('/sessions/{session}/assessment')->name('assessment.')->controller(AssessmentController::class)->group(function () {
        Route::get('/ahp', 'editAhp')->name('ahp');
        Route::put('/ahp', 'updateAhp')->name('ahp.update');
        Route::get('/scores', 'editScores')->name('scores');
        Route::put('/scores', 'updateScores')->name('scores.update');
        Route::get('/result', 'result')->name('result');
        Route::post('/submit', 'submit')->name('submit');
        Route::post('/withdraw', 'withdraw')->name('withdraw');
    });

    // Voting (H10, V1) dan hasil konsensus (H11).
    Route::get('/sessions/{session}/vote', [VoteController::class, 'show'])->name('vote.show');
    Route::post('/sessions/{session}/vote', [VoteController::class, 'store'])->name('vote.store');
    Route::get('/sessions/{session}/consensus', [ConsensusController::class, 'show'])->name('consensus.show');

    Route::middleware('leader')->group(function () {
        Route::post('/sessions', [SessionController::class, 'store'])->name('sessions.store');
        Route::get('/sessions/{session}/control', [LeaderPanelController::class, 'show'])->name('control.show');
        Route::post('/sessions/{session}/open-assessment', [WorkflowController::class, 'openAssessment'])->name('sessions.open-assessment');
        Route::post('/sessions/{session}/start-voting', [WorkflowController::class, 'startVoting'])->name('sessions.start-voting');
        Route::post('/sessions/{session}/deliberation', [WorkflowController::class, 'deliberate'])->name('sessions.deliberate');
        Route::post('/sessions/{session}/reopen', [WorkflowController::class, 'reopen'])->name('sessions.reopen');
        Route::post('/sessions/{session}/finalize', [WorkflowController::class, 'finalize'])->name('sessions.finalize');
    });
});
