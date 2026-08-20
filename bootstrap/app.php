<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Clôture journalière : porte sur la veille, donc à une heure où
        // plus rien ne bouge. Le planificateur tourne déjà dans le conteneur
        // (supervisord lance schedule:work) — rien à installer.
        $schedule->command('night-audit:run')
            ->dailyAt('04:00')
            ->withoutOverlapping();

        // Alerte de verrouillage. Volontairement SANS --force : le
        // verrouillage est définitif, il reste un geste humain. La commande
        // se contente de signaler ce qui dépasse le délai de l'Article 22.
        $schedule->command('ledger:lock-periods')
            ->monthlyOn(5, '06:00');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\TrackUserOnlineStatus::class,
            \App\Http\Middleware\LogUserActivity::class,
        ]);

        // RBAC Middleware aliases
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRoleAccess::class,
            'admin' => \App\Http\Middleware\AdminOnly::class,
            'module' => \App\Http\Middleware\EnsureModuleEnabled::class,
            'module.access' => \App\Http\Middleware\EnsureModuleWriteAccess::class,
            'caisse' => \App\Http\Middleware\EnsureCashRegisterOpen::class,
            'reporting.token' => \App\Http\Middleware\ValidateReportingToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
