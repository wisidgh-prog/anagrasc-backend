<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\Incident;
use App\Models\SessionEnchere;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard
     * auth:sanctum + role:administrateur
     * Indicateurs principaux pour le tableau de bord de l'administrateur.
     */
    public function admin()
    {
        // Nombre de sessions en cours en ce moment
        $encheres_en_cours = SessionEnchere::where('statut', SessionEnchere::STATUT_EN_COURS)->count();

        // Nombre de sessions planifiées mais pas encore démarrées
        $encheres_a_venir = SessionEnchere::where('statut', SessionEnchere::STATUT_A_VENIR)->count();

        // Nombre total d'enchérisseurs inscrits
        $encherisseurs_total = User::whereHas('role', function ($q) {
            $q->where('nom', 'encherisseur');
        })->count();

        // Nombre de comptes enchérisseurs en attente de validation
        $comptes_en_attente = User::whereHas('role', function ($q) {
            $q->where('nom', 'encherisseur');
        })
        ->where('est_valide', false)
        ->where('est_suspendu', false)
        ->count();

        // Total des recettes du mois en cours (paiements finaux)
        $recettes_du_mois = Transaction::where('type', 'paiement_final')
            ->whereMonth('created_at', now()->month)
            ->sum('montant');

        // Total des cautions actuellement bloquées (sessions non terminées)
        $cautions_bloquees = Transaction::where('type', 'prelevement_caution')
            ->whereHas('session', function ($q) {
                $q->whereIn('statut', ['a_venir', 'en_cours', 'interrompue']);
            })
            ->sum('montant');

        // Les 5 dernières sessions créées (pour le résumé rapide)
        $dernieres_sessions = SessionEnchere::with('bien:id,titre')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'bien_id', 'statut', 'created_at']);

        return response()->json([
            'data' => [
                'encheres_en_cours'   => $encheres_en_cours,
                'encheres_a_venir'    => $encheres_a_venir,
                'encherisseurs_total' => $encherisseurs_total,
                'comptes_en_attente'  => $comptes_en_attente,
                'recettes_du_mois'    => abs($recettes_du_mois),
                'cautions_bloquees'   => abs($cautions_bloquees),
                'dernieres_sessions'  => $dernieres_sessions,
            ],
        ]);
    }

    /**
     * GET /api/commissaire/dashboard
     * auth:sanctum + role:commissaire_priseur
     * Indicateurs du tableau de bord du commissaire-priseur connecté.
     */
    public function commissaire(Request $request)
    {
        $userId = $request->user()->id;

        // Sessions en cours créées par ce commissaire
        $sessions_en_cours = SessionEnchere::where('commissaire_id', $userId)
            ->where('statut', SessionEnchere::STATUT_EN_COURS)
            ->count();

        // Sessions planifiées par ce commissaire
        $sessions_a_venir = SessionEnchere::where('commissaire_id', $userId)
            ->where('statut', SessionEnchere::STATUT_A_VENIR)
            ->count();

        // Biens actifs (publiés ou en cours d'enchère) créés par ce commissaire
        $biens_actifs = Bien::where('cree_par', $userId)
            ->whereIn('statut', [Bien::STATUT_PUBLIE, Bien::STATUT_EN_COURS])
            ->count();

        // Incidents ouverts sur les sessions de ce commissaire
        $incidents_ouverts = Incident::whereHas('session', function ($q) use ($userId) {
            $q->where('commissaire_id', $userId);
        })
        ->where('statut', 'ouvert')
        ->count();

        return response()->json([
            'data' => [
                'sessions_en_cours' => $sessions_en_cours,
                'sessions_a_venir'  => $sessions_a_venir,
                'biens_actifs'      => $biens_actifs,
                'incidents_ouverts' => $incidents_ouverts,
            ],
        ]);
    }
}
