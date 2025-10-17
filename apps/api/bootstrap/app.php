<?php

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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.resolve' => App\Http\Middleware\ResolveTenant::class,
            'feature' => App\Http\Middleware\EnsureFeatureIsEnabled::class,
            'audit' => App\Http\Middleware\AuditRequest::class,
        ]);

        $middleware->prependToGroup('api', App\Http\Middleware\ResolveTenant::class);
        $middleware->appendToGroup('api', App\Http\Middleware\AuditRequest::class);
    })
    ->withProviders([
        App\Providers\TenancyServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
