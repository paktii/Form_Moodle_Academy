<?php

use App\Http\Middleware\EnsurePortalRole;
use App\Http\Middleware\DiscardPortalDraftWhenLeavingForm;
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
            'portal.draft.lifecycle' => DiscardPortalDraftWhenLeavingForm::class,
            'portal.role' => EnsurePortalRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
