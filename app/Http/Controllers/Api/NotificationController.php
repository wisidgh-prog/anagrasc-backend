<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Notifications internes (in-app) de l'utilisateur connecté.
 *
 * Alimente la cloche de la navbar : liste, compteur de non-lues,
 * marquage individuel et global comme lu.
 */
class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     * auth:sanctum — liste les 30 dernières notifications de l'utilisateur.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn ($n) => [
                'id'         => $n->id,
                'titre'      => $n->data['titre'] ?? 'Notification',
                'message'    => $n->data['message'] ?? '',
                'lien'       => $n->data['lien'] ?? null,
                'icone'      => $n->data['icone'] ?? 'notifications',
                'categorie'  => $n->data['categorie'] ?? 'info',
                'lu'         => $n->read_at !== null,
                'date'       => $n->created_at?->diffForHumans(),
                'created_at' => $n->created_at,
            ]);

        return response()->json([
            'data'     => $notifications,
            'non_lues' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * GET /api/notifications/compteur
     * auth:sanctum — renvoie uniquement le nombre de notifications non lues
     * (requête légère pour le polling du badge).
     */
    public function compteur(Request $request)
    {
        return response()->json([
            'non_lues' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * POST /api/notifications/{notification}/lire
     * Marque une notification comme lue.
     */
    public function marquerLue(Request $request, string $notification)
    {
        $n = $request->user()->notifications()->whereKey($notification)->first();

        if ($n && $n->read_at === null) {
            $n->markAsRead();
        }

        return response()->json(['message' => 'Notification marquée comme lue.']);
    }

    /**
     * POST /api/notifications/tout-lire
     * Marque toutes les notifications non lues comme lues.
     */
    public function toutMarquerLues(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Toutes les notifications ont été marquées comme lues.']);
    }
}
