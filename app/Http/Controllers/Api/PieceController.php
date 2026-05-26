<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class PieceController extends Controller
{
    /**
     * GET /api/pieces/{user}
     * auth:sanctum + role:administrateur
     *
     * Retourne les chemins des pièces d'identité d'un utilisateur.
     * Seul l'administrateur peut y accéder.
     */
    public function show(User $user)
    {
        $recto = $user->piece_recto_url
            ? Storage::disk('local')->path($user->piece_recto_url)
            : null;

        $verso = $user->piece_verso_url
            ? Storage::disk('local')->path($user->piece_verso_url)
            : null;

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'nom_complet' => $user->nom_complet,
                'type_piece'  => $user->type_piece,
                'num_piece'   => $user->num_piece,
                'recto'       => $recto,
                'verso'       => $verso,
            ],
        ]);
    }
}
