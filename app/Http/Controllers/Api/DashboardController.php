<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\SessionEnchere;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard
     * auth:sanctum + role:administrateur
     *
     * Indicateurs principaux pour l'administrateur (CDC Module 7).
     */
    public function admin()
    {
        return response()->json([
            'data' => [
                'encheres_en_cours'   => SessionEnchere::where('statut', SessionEnchere::STATUT_EN_COURS)->count(),
                'encheres_a_venir'    => SessionEnchere::where('statut', SessionEnchere::STATUT_A_VENIR)->count(),
                'encherisseurs_total' => User::whereHas('role', fn($q) => $q->where('nom', 'encherisseur'))->count(),
                'comptes_en_attente'  => User::whereHas('role', fn($q) => $q->where('nom', 'encherisseur'))
                                            ->where('est_valide', false)
                                            ->where('est_suspendu', false)
                                            ->count(),
                'recettes_du_mois'    => abs(Transaction::where('type', 'paiement_final')
                                            ->whereMonth('created_at', now()->month)
                                            ->sum('montant')),
                'cautions_bloquees'   => abs(Transaction::where('type', 'prelevement_caution')
                                            ->whereHas('session', fn($q) => $q->whereIn('statut', ['a_venir', 'en_cours', 'interrompue']))
                                            ->sum('montant')),
                'dernieres_sessions'  => SessionEnchere::with('bien:id,titre')
                                            ->orderByDesc('created_at')
                                            ->limit(5)
                                            ->get(['id', 'bien_id', 'statut', 'created_at']),
            ],
        ]);
    }

    /**
     * GET /api/commissaire/dashboard
     * auth:sanctum + role:commissaire_priseur
     *
     * Indicateurs pour le commissaire connecté.
     */
    public function commissaire(Request $request)
    {
        $userId = $request->user()->id;

        return response()->json([
            'data' => [
                'sessions_en_cours'  => SessionEnchere::where('commissaire_id', $userId)
                                            ->where('statut', SessionEnchere::STATUT_EN_COURS)
                                            ->count(),
                'sessions_a_venir'   => SessionEnchere::where('commissaire_id', $userId)
                                            ->where('statut', SessionEnchere::STATUT_A_VENIR)
                                            ->count(),
                'biens_actifs'       => Bien::where('cree_par', $userId)
                                            ->whereIn('statut', [Bien::STATUT_PUBLIE, Bien::STATUT_EN_COURS])
                                            ->count(),
                'incidents_ouverts'  => \App\Models\Incident::whereHas('session', fn($q) => $q->where('commissaire_id', $userId))
                                            ->where('statut', 'ouvert')
                                            ->count(),
            ],
        ]);
    }
}
