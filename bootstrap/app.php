<?php

use App\Http\Middleware\EnsureMerchantManager;
use App\Http\Middleware\EnsureMerchantMember;
use App\Http\Middleware\EnsurePlatformAdministrator;
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
            'merchant.member' => EnsureMerchantMember::class,
            'merchant.manage' => EnsureMerchantManager::class,
            'platform.admin' => EnsurePlatformAdministrator::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
