<?php

namespace App\Http\Controllers\Api;

use App\Models\Bien;
use App\Models\SessionEnchere;
use App\Http\Controllers\Controller;
use App\Notifications\NouveauBienNotification;
use App\Notifications\ResultatEnchereNotification;
use App\Notifications\SessionAnnuleeNotification;
use App\Notifications\SessionReporteeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * Endpoint de vérification du système d'emails.
 *
 * N'existe que pour les tests : il envoie UN SEUL email (à wisidgh@gmail.com
 * par défaut) afin de respecter les quotas SMTP. Les flux de production, eux,
 * passent par NotificationService.
 */
class MailTestController extends Controller
{
    /**
     * POST /api/mail-test
     * auth:sanctum + role:administrateur
     *
     * Body :
     *   - template          : nouveau_bien | enchere_gagnee | enchere_resultat | annulation | report
     *   - destinataire      : email de réception (défaut : wisidgh@gmail.com)
     *   - session_id        : session utilisée (défaut : session #12, la démo)
     *   - nouvelle_session_id : session "reportée" pour le template report
     */
    public function envoyer(Request $request)
    {
        $request->validate([
            'template'            => 'required|in:nouveau_bien,enchere_gagnee,enchere_resultat,annulation,report',
            'destinataire'        => 'nullable|email',
            'session_id'          => 'nullable|exists:sessions_enchere,id',
            'nouvelle_session_id' => 'nullable|exists:sessions_enchere,id',
        ]);

        $destinataire = $request->destinataire ?? 'wisidgh@gmail.com';
        $session      = SessionEnchere::with('bien')->find($request->session_id ?? 12);
        $notification = null;

        switch ($request->template) {
            case 'nouveau_bien':
                $bien = Bien::find($request->session_id ? $session->bien_id : 5) ?? $session->bien;
                $notification = new NouveauBienNotification($bien);
                break;

            case 'enchere_gagnee':
                $notification = new ResultatEnchereNotification($session, true, 130000, 'ENCH-003');
                break;

            case 'enchere_resultat':
                $notification = new ResultatEnchereNotification($session, false, 120000, 'ENCH-002');
                break;

            case 'annulation':
                $notification = new SessionAnnuleeNotification($session, 'Test de vérification du mail d\'annulation.');
                break;

            case 'report':
                $nouvelle = SessionEnchere::find($request->nouvelle_session_id ?? $session->id);
                $notification = new SessionReporteeNotification($session, $nouvelle, 'Test de vérification du mail de report.');
                break;
        }

        if (!$notification) {
            return response()->json(['message' => 'Template inconnu.'], 422);
        }

        Notification::route('mail', $destinataire)->notify($notification);

        return response()->json([
            'message' => "Email '{$request->template}' envoyé à {$destinataire} (1 seul envoi).",
        ]);
    }
}
