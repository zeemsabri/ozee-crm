<?php

use App\Http\Middleware\CheckPermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

//        $middleware->statefulApi(); // For Sanctum SPA authentication
//
//        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'permission' => CheckPermission::class,
            'auth.magiclink' => \App\Http\Middleware\VerifyMagicLinkToken::class,
            'process.tags' => \App\Http\Middleware\ProcessTags::class,
            'google.chat.auth' => \App\Http\Middleware\AuthenticateGoogleChat::class,
            'process.basic' =>  \App\Http\Middleware\ProcessBasicProperty::class,
            'permissionInAnyProject' => \App\Http\Middleware\CheckPermissionInAnyProject::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
