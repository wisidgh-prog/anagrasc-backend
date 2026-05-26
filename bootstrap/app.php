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
    ->withMiddleware(function (Middleware $middleware) {
        // 1. Ajoute le middleware Sanctum pour les API avec authentification par cookies
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // 2. Définit des alias pour nos futurs middlewares personnalisés
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'encherisseur.valide' => \App\Http\Middleware\CheckEncherisseurValide::class,
        ]);
        //3. redirige les utilisateurs non authentifiés vers une réponse JSON au lieu de la page de login
    $middleware->redirectGuestsTo(fn () => response()->json(['message' => 'Unauthenticated.'], 401));

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
