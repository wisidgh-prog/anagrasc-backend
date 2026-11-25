<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppLog;
use App\Models\Configuration;
use App\Models\Encherisseur;
use App\Models\SessionEnchere;
use App\Models\Transaction;
use App\Services\NotificationInAppService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CautionsPaiementsController extends Controller
{
    public function __construct(
        private WalletService $walletService,
        private NotificationInAppService $notifications,
    ) {}

    /**
     * GET /api/admin/cautions-paiements
     * auth:sanctum + role:administrateur
     *
     * Vue d'ensemble : cautions actuellement bloquées (participations de sessions
     * ouvertes + caution du gagnant des sessions terminées non payées) et liste
     * des sessions terminées restant à régler.
     */
    public function index()
    {
        // 1) Cautions prélevées sur des sessions encore ouvertes
        $participations = Transaction::where('type', Transaction::TYPE_PRELEVEMENT_CAUTION)
            ->whereHas('session', function ($q) {
                $q->whereIn('statut', [
                    SessionEnchere::STATUT_A_VENIR,
                    SessionEnchere::STATUT_EN_COURS,
                    SessionEnchere::STATUT_INTERROMPUE,
                ]);
            })
            ->with([
                'encherisseur.personne:id,nom,prenom,type_personne,raison_sociale,telephone',
                'session.bien:id,titre',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($t) => [
                'type'            => 'participation',
                'transaction_id'  => $t->id,
                'encherisseur_id' => $t->encherisseur_id,
                'encherisseur'    => $t->encherisseur?->personne?->nom_complet,
                'telephone'       => $t->encherisseur?->personne?->telephone,
                'session_id'      => $t->session_id,
                'bien'            => $t->session?->bien?->titre,
                'statut_session'  => $t->session?->statut,
                'montant'         => abs((float) $t->montant),
                'date'            => $t->created_at,
            ]);

        // 2) Caution du gagnant des sessions terminées mais non réglées
        $gagnantsImpayes = SessionEnchere::where('statut', SessionEnchere::STATUT_TERMINEE)
            ->whereNull('paiement_effectue_le')
            ->whereNotNull('offre_gagnante_id')
            ->with(['offreGagnante.encherisseur.personne', 'bien:id,titre'])
            ->get()
            ->map(function ($s) {
                $encherisseur = $s->offreGagnante?->encherisseur;

                return [
                    'type'            => 'gagnant_impaye',
                    'transaction_id'  => null,
                    'encherisseur_id' => $encherisseur?->id,
                    'encherisseur'    => $encherisseur?->personne?->nom_complet,
                    'telephone'       => $encherisseur?->personne?->telephone,
                    'session_id'      => $s->id,
                    'bien'            => $s->bien?->titre,
                    'statut_session'  => $s->statut,
                    'montant'         => $s->getCautionRequise(),
                    'date'            => $s->date_fin,
                ];
            });

        $cautions = $participations->concat($gagnantsImpayes)->values();

        // 3) Sessions terminées non payées (à régler)
        $tauxFrais = (float) Configuration::valeur('taux_frais_adjudication', 8);

        $nonPayees = SessionEnchere::where('statut', SessionEnchere::STATUT_TERMINEE)
            ->whereNull('paiement_effectue_le')
            ->whereNotNull('offre_gagnante_id')
            ->with(['offreGagnante.encherisseur.personne', 'bien:id,titre', 'commissaire:id,nom,prenom'])
            ->orderByDesc('date_fin')
            ->get()
            ->map(function ($s) use ($tauxFrais) {
                $prix    = (float) ($s->offreGagnante?->montant ?? 0);
                $frais   = round($prix * $tauxFrais / 100, 2);
                $caution = $s->getCautionRequise();
                $gagnant = $s->offreGagnante?->encherisseur?->personne;

                return [
                    'session_id'  => $s->id,
                    'bien'        => $s->bien?->titre,
                    'commissaire' => $s->commissaire?->nom_complet,
                    'gagnant'     => $gagnant?->nom_complet,
                    'telephone'   => $gagnant?->telephone,
                    'prix'        => $prix,
                    'frais'       => $frais,
                    'caution'     => $caution,
                    'total'       => max(0, round($prix + $frais - $caution, 2)),
                    'date_fin'    => $s->date_fin,
                ];
            });

        return response()->json([
            'data' => [
                'cautions_bloquees'       => $cautions,
                'sessions_non_payees'     => $nonPayees,
                'total_cautions_bloquees' => round((float) $cautions->sum('montant'), 2),
                'total_a_encaisser'       => round((float) $nonPayees->sum('total'), 2),
            ],
        ]);
    }

    /**
     * GET /api/admin/sessions
     * auth:sanctum + role:administrateur
     *
     * Fiche de toutes les sessions d'enchères avec les informations de paiement
     * et de caution, pour piloter les actions depuis l'administration.
     */
    public function sessions()
    {
        $tauxFrais = (float) Configuration::valeur('taux_frais_adjudication', 8);

        $sessions = SessionEnchere::with([
                'bien:id,titre',
                'commissaire:id,nom,prenom',
                'offreGagnante.encherisseur.personne:id,nom,prenom,type_personne,raison_sociale,telephone',
            ])
            ->orderByDesc('date_debut')
            ->get()
            ->map(function ($s) use ($tauxFrais) {
                $encherisseurId = $s->offreGagnante?->encherisseur_id;
                $gagnant        = $s->offreGagnante?->encherisseur?->personne;

                $prix    = (float) ($s->offreGagnante?->montant ?? 0);
                $frais   = round($prix * $tauxFrais / 100, 2);
                $caution = $s->getCautionRequise();

                $cautionPrelevee   = false;
                $cautionRemboursee = false;

                if ($encherisseurId) {
                    $cautionPrelevee = Transaction::where('encherisseur_id', $encherisseurId)
                        ->where('session_id', $s->id)
                        ->where('type', Transaction::TYPE_PRELEVEMENT_CAUTION)
                        ->exists();

                    $cautionRemboursee = Transaction::where('encherisseur_id', $encherisseurId)
                        ->where('session_id', $s->id)
                        ->where('type', Transaction::TYPE_REMBOURSEMENT_CAUTION)
                        ->exists();
                }

                return [
                    'id'                => $s->id,
                    'bien'              => $s->bien?->titre,
                    'commissaire'       => $s->commissaire?->nom_complet,
                    'statut'            => $s->statut,
                    'gagnant'           => $gagnant?->nom_complet,
                    'telephone'         => $gagnant?->telephone,
                    'encherisseur_id'   => $encherisseurId,
                    'prix'              => $prix,
                    'frais'             => $frais,
                    'caution'           => $caution,
                    'total'             => max(0, round($prix + $frais - $caution, 2)),
                    'paiement_effectue' => (bool) $s->paiement_effectue_le,
                    'paiement_le'       => $s->paiement_effectue_le,
                    'date_debut'        => $s->date_debut,
                    'date_fin'          => $s->date_fin,
                    'peut_payer'        => $s->statut === SessionEnchere::STATUT_TERMINEE
                                                && $s->offre_gagnante_id
                                                && !$s->paiement_effectue_le,
                    'peut_liberer'      => $cautionPrelevee && !$cautionRemboursee,
                ];
            });

        return response()->json(['data' => $sessions]);
    }

    /**
     * POST /api/admin/sessions/{session}/confirmer-paiement
     * auth:sanctum + role:administrateur
     *
     * Confirme manuellement qu'une session terminée a été réglée (paiement
     * encaissé hors wallet : espèces, virement...). Enregistre la recette et
     * marque le bien comme vendu. Aucun débit du wallet n'est effectué.
     */
    public function confirmerPaiement(Request $request, SessionEnchere $session)
    {
        if ($session->statut !== SessionEnchere::STATUT_TERMINEE || !$session->offre_gagnante_id) {
            return response()->json(['message' => 'Aucun paiement n\'est attendu pour cette session.'], 422);
        }

        if ($session->paiement_effectue_le) {
            return response()->json(['message' => 'Le paiement a déjà été confirmé.'], 422);
        }

        $offreGagnante = $session->offreGagnante;

        if (!$offreGagnante || !$offreGagnante->encherisseur) {
            return response()->json(['message' => 'Gagnant introuvable pour cette session.'], 422);
        }

        $encherisseur = $offreGagnante->encherisseur;

        $prix    = (float) $offreGagnante->montant;
        $taux    = (float) Configuration::valeur('taux_frais_adjudication', 8);
        $frais   = round($prix * $taux / 100, 2);
        $caution = $session->getCautionRequise();
        $total   = max(0, round($prix + $frais - $caution, 2));

        $transaction = DB::transaction(function () use ($session, $encherisseur, $total, $request, $prix, $frais, $caution) {
            $session = SessionEnchere::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($session->paiement_effectue_le) {
                return null;
            }

            $session->update(['paiement_effectue_le' => now()]);
            $session->bien->update(['statut' => 'vendu']);

            // Recette enregistrée (paiement hors wallet : pas de débit du solde)
            $transaction = Transaction::create([
                'encherisseur_id' => $encherisseur->id,
                'session_id'      => $session->id,
                'type'            => Transaction::TYPE_PAIEMENT_FINAL,
                'montant'         => $total,
                'statut'          => Transaction::STATUT_OK,
                'description'     => 'Paiement final confirmé manuellement (administrateur)',
            ]);

            AppLog::create([
                'user_id'     => $request->user()->id,
                'action'      => 'confirmation_paiement_manuelle',
                'description' => "Paiement de la session #{$session->id} confirmé manuellement : {$total} FCFA (prix {$prix} + frais {$frais} - caution {$caution})",
                'horodatage'  => now(),
            ]);

            return $transaction;
        });

        if (!$transaction) {
            return response()->json(['message' => 'Le paiement a déjà été confirmé.'], 422);
        }

        // Prévenir le gagnant
        $this->notifications->envoyer(
            $encherisseur->personne,
            'Paiement confirmé',
            "Votre paiement de {$total} FCFA pour la session #{$session->id} a été confirmé.",
            '/mon-espace',
            'payments',
            'paiement'
        );

        return response()->json([
            'message'   => 'Paiement confirmé.',
            'prix'      => $prix,
            'frais'     => $frais,
            'caution'   => $caution,
            'total'     => $total,
            'reference' => $transaction->id,
        ]);
    }

    /**
     * POST /api/admin/cautions/liberer
     * auth:sanctum + role:administrateur
     * Body : { session_id, encherisseur_id }
     * Libère (rembourse) la caution d'un enchérisseur pour une session donnée.
     * Idempotent : une caution déjà remboursée n'est pas recréditée.
     */
    public function libererCaution(Request $request)
    {
        $validated = $request->validate([
            'session_id'      => 'required|integer|exists:sessions_enchere,id',
            'encherisseur_id' => 'required|integer|exists:encherisseurs,id',
        ]);

        $session      = SessionEnchere::findOrFail($validated['session_id']);
        $encherisseur = Encherisseur::findOrFail($validated['encherisseur_id']);

        // Montant réellement prélevé pour cette session
        $prelevement = Transaction::where('encherisseur_id', $encherisseur->id)
            ->where('session_id', $session->id)
            ->where('type', Transaction::TYPE_PRELEVEMENT_CAUTION)
            ->latest('id')
            ->first();

        $montant = $prelevement
            ? abs((float) $prelevement->montant)
            : $session->getCautionRequise();

        $dejaRembourse = Transaction::where('encherisseur_id', $encherisseur->id)
            ->where('session_id', $session->id)
            ->where('type', Transaction::TYPE_REMBOURSEMENT_CAUTION)
            ->exists();

        if ($dejaRembourse) {
            return response()->json(['message' => 'Cette caution a déjà été libérée.'], 422);
        }

        $transaction = $this->walletService->rembourser($encherisseur, $montant, $session->id);

        AppLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'liberation_caution',
            'description' => "Caution de {$montant} FCFA libérée pour l'enchérisseur #{$encherisseur->id} (session #{$session->id})",
            'horodatage'  => now(),
        ]);

        // Notifier l'enchérisseur
        $this->notifications->envoyer(
            $encherisseur->personne,
            'Caution libérée',
            "Votre caution de {$montant} FCFA a été recréditée sur votre portefeuille.",
            '/mon-espace/wallet',
            'account_balance_wallet',
            'caution'
        );

        return response()->json([
            'message'   => 'Caution libérée.',
            'montant'   => $montant,
            'reference' => $transaction->id,
        ]);
    }
}
