<?php

use App\Exceptions\WorkflowException;
use App\Http\Middleware\EnsureUserIsLeader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'leader' => EnsureUserIsLeader::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Transisi yang ditolak (Bab 7.6): kembali ke halaman sebelumnya dengan pesan alasan.
        $exceptions->render(fn (WorkflowException $e) => back()->withInput()->with('error', $e->getMessage()));
    })->create();
