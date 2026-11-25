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
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // API sans page de connexion : on n'a jamais de redirection "guests"
        // (sinon route('login') lève une exception avant le rendu JSON).
        $middleware->redirectGuestsTo(fn () => null);

        // Alias pour les middlewares (optionnel, mais on les garde)
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'encherisseur.valide' => \App\Http\Middleware\CheckEncherisseurValide::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        // L'API ne doit jamais tenter de rediriger vers une page web
        // (aucune route "login" n'existe) : toutes les routes /api/* répondent en JSON.
        $exceptions->shouldRenderJsonWhen(
            fn ($request, $e) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
