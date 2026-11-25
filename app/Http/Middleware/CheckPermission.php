<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Vérifie que l'utilisateur connecté possède AU MOINS UNE des permissions
     * passées en paramètre.
     *
     * Exemple de route : ->middleware('permission:gerer_biens,gerer_sessions')
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'message'              => 'Accès non autorisé : permission manquante.',
            'permissions_requises' => $permissions,
        ], 403);
    }
}
