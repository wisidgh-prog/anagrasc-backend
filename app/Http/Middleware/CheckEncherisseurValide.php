<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckEncherisseurValide
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */

    
    /**
     * Vérifie que l'utilisateur connecté est un enchérisseur valide
     * (compte validé, non suspendu).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isEncherisseur() || !$user->est_valide || $user->est_suspendu) {
            return response()->json(['message' => 'Compte enchérisseur non valide.'], 403);
        }

        return $next($request);
    }
}
