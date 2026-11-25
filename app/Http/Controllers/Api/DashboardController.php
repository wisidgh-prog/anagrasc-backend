<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\Configuration;
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

        // Synthèse financière globale (le détail mensuel est sur la page Finance)
        $finance = $this->financeGenerale();

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
                'finance'             => $finance,
                'dernieres_sessions'  => $dernieres_sessions,
            ],
        ]);
    }

    /**
     * GET /api/admin/finance
     * auth:sanctum + role:administrateur
     * Détail financier mois par mois + totaux généraux.
     */
    public function finance()
    {
        $tauxFrais = (float) Configuration::valeur('taux_frais_adjudication', 8);

        // Adjudications groupées par mois (mois de la date de fin de session)
        $adjudications = SessionEnchere::where('statut', SessionEnchere::STATUT_TERMINEE)
            ->whereNotNull('offre_gagnante_id')
            ->with('offreGagnante:id,montant')
            ->get(['id', 'date_fin', 'offre_gagnante_id'])
            ->groupBy(fn ($s) => $s->date_fin ? $s->date_fin->format('Y-m') : 'inconnu')
            ->map(function ($groupe) use ($tauxFrais) {
                $montant = (float) $groupe->sum(fn ($s) => (float) ($s->offreGagnante?->montant ?? 0));
                return [
                    'montants_adjuges'   => round($montant, 2),
                    'frais_adjudication' => round($montant * $tauxFrais / 100, 2),
                ];
            });

        // Transactions groupées par mois selon leur type
        $parMoisTransaction = function (string $type) {
            return Transaction::where('type', $type)
                ->get(['montant', 'created_at'])
                ->groupBy(fn ($t) => $t->created_at ? $t->created_at->format('Y-m') : 'inconnu')
                ->map(fn ($rows) => round((float) $rows->sum('montant'), 2));
        };

        $paiements           = $parMoisTransaction(Transaction::TYPE_PAIEMENT_FINAL);
        $cautionsPrelevees   = $parMoisTransaction(Transaction::TYPE_PRELEVEMENT_CAUTION);
        $cautionsRemboursees = $parMoisTransaction(Transaction::TYPE_REMBOURSEMENT_CAUTION);

        // Union de tous les mois ayant une activité, du plus récent au plus ancien
        $mois = collect()
            ->merge($adjudications->keys())
            ->merge($paiements->keys())
            ->merge($cautionsPrelevees->keys())
            ->merge($cautionsRemboursees->keys())
            ->reject(fn ($m) => $m === 'inconnu')
            ->unique()
            ->sortDesc()
            ->values();

        $parMois = $mois->map(function ($ym) use ($adjudications, $paiements, $cautionsPrelevees, $cautionsRemboursees) {
            $adj  = $adjudications->get($ym, ['montants_adjuges' => 0, 'frais_adjudication' => 0]);
            $date = \Illuminate\Support\Carbon::parse($ym . '-01');

            return [
                'mois'                 => $ym,
                'libelle'              => $this->libelleMois($date),
                'montants_adjuges'     => abs($adj['montants_adjuges']),
                'frais_adjudication'   => abs($adj['frais_adjudication']),
                'paiements_finaux'     => abs((float) $paiements->get($ym, 0)),
                'cautions_prelevees'   => abs((float) $cautionsPrelevees->get($ym, 0)),
                'cautions_remboursees' => abs((float) $cautionsRemboursees->get($ym, 0)),
            ];
        })->values();

        return response()->json([
            'data' => [
                'par_mois' => $parMois,
                'general'  => $this->financeGenerale(),
            ],
        ]);
    }

    /**
     * Totaux financiers généraux (toutes périodes confondues).
     */
    private function financeGenerale(): array
    {
        $tauxFrais = (float) Configuration::valeur('taux_frais_adjudication', 8);

        $montantsAdjuges = (float) SessionEnchere::where('statut', SessionEnchere::STATUT_TERMINEE)
            ->whereNotNull('offre_gagnante_id')
            ->with('offreGagnante:id,montant')
            ->get()
            ->sum(fn ($s) => (float) ($s->offreGagnante?->montant ?? 0));

        return [
            'montants_adjuges'     => abs($montantsAdjuges),
            'frais_adjudication'   => abs(round($montantsAdjuges * $tauxFrais / 100, 2)),
            'paiements_finaux'     => abs((float) Transaction::where('type', Transaction::TYPE_PAIEMENT_FINAL)->sum('montant')),
            'cautions_prelevees'   => abs((float) Transaction::where('type', Transaction::TYPE_PRELEVEMENT_CAUTION)->sum('montant')),
            'cautions_remboursees' => abs((float) Transaction::where('type', Transaction::TYPE_REMBOURSEMENT_CAUTION)->sum('montant')),
            'cautions_bloquees'    => $this->cautionsBloquees(),
        ];
    }

    /**
     * Montant des cautions actuellement bloquées :
     * - participations de sessions encore ouvertes (à venir / en cours / interrompues)
     * - caution du gagnant de sessions terminées mais non encore réglées
     */
    private function cautionsBloquees(): float
    {
        $actives = (float) Transaction::where('type', Transaction::TYPE_PRELEVEMENT_CAUTION)
            ->whereHas('session', function ($q) {
                $q->whereIn('statut', [
                    SessionEnchere::STATUT_A_VENIR,
                    SessionEnchere::STATUT_EN_COURS,
                    SessionEnchere::STATUT_INTERROMPUE,
                ]);
            })
            ->sum('montant');

        $gagnantsImpayes = (float) SessionEnchere::where('statut', SessionEnchere::STATUT_TERMINEE)
            ->whereNull('paiement_effectue_le')
            ->whereNotNull('offre_gagnante_id')
            ->get()
            ->sum(fn ($s) => $s->getCautionRequise());

        return abs($actives) + $gagnantsImpayes;
    }

    /**
     * Libellé français du mois d'une date (ex. "octobre 2026"), sans dépendre de la locale Carbon.
     */
    private function libelleMois(\DateTimeInterface $date): string
    {
        $mois = [
            1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
        ];

        return $mois[(int) $date->format('n')] . ' ' . $date->format('Y');
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

        // Biens publiés (publiés ou en cours d'enchère) créés par ce commissaire
        $biens_publies = Bien::where('cree_par', $userId)
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
                'biens_publies'      => $biens_publies,
                'incidents_ouverts' => $incidents_ouverts,
            ],
        ]);
    }
}
