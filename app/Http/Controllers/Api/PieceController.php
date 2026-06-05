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
     * Retourne les informations et chemins des pièces d'identité d'un utilisateur.
     */
    public function show(User $user)
    {
        // Storage::disk('local')->path() retourne le chemin absolu du fichier sur le serveur
        $recto = null;
        if ($user->piece_recto_url) {
            $recto = Storage::disk('local')->path($user->piece_recto_url);
        }

        $verso = null;
        if ($user->piece_verso_url) {
            $verso = Storage::disk('local')->path($user->piece_verso_url);
        }

        return response()->json([
            'data' => [
                'user_id'     => $user->id,
                'nom_complet' => $user->nom_complet,
                'type_piece'  => $user->type_piece,
                'num_piece'   => $user->num_piece,
                'recto'       => $recto,
                'verso'       => $verso,
            ],
        ]);
    }

    /**
     * GET /api/pieces/{user}/recto
     * auth:sanctum + role:administrateur
     * Affiche directement le fichier recto de la pièce d'identité dans le navigateur.
     */
    public function afficherRecto(User $user)
    {
        return $this->servirFichier($user, 'recto');
    }

    /**
     * GET /api/pieces/{user}/verso
     * auth:sanctum + role:administrateur
     * Affiche directement le fichier verso de la pièce d'identité dans le navigateur.
     */
    public function afficherVerso(User $user)
    {
        return $this->servirFichier($user, 'verso');
    }

    /**
     * Méthode privée — envoie le fichier (recto ou verso) au navigateur.
     * Utilisée par afficherRecto() et afficherVerso() pour éviter la répétition.
     */
    private function servirFichier(User $user, string $face)
    {
        // Choisir l'URL selon la face demandée
        $url = ($face === 'recto') ? $user->piece_recto_url : $user->piece_verso_url;

        // Vérifier que le fichier existe bien
        if (!$url || !Storage::disk('local')->exists($url)) {
            abort(404, 'Pièce introuvable');
        }

        // response()->file() envoie directement le fichier au navigateur
        return response()->file(Storage::disk('local')->path($url));
    }
}
