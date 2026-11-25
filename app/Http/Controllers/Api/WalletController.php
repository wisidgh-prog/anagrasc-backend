<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppLog;
use App\Models\Transaction;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    // Le WalletService est injecté automatiquement par Laravel
    // Il contient la logique pour débiter, créditer et consulter le solde
    public function __construct(private WalletService $walletService) {}

    /**
     * GET /api/wallet
     * auth:sanctum + role:encherisseur (validé)
     * Retourne le solde du wallet et le nombre de sessions actives.
     */
    public function show(Request $request)
    {
        $user   = $request->user()->load('encherisseur.wallet');
        $wallet = $user->encherisseur?->wallet;

        if (!$wallet) {
            return response()->json(['message' => 'Portefeuille introuvable.'], 404);
        }

        // Compter le nombre de sessions distinctes où cet enchérisseur a participé
        $nbParticipations = $user->encherisseur
            ->offres()
            ->distinct('session_id')
            ->count('session_id');

        return response()->json([
            'data' => [
                'solde'             => (float) $wallet->solde,
                'nb_participations' => $nbParticipations,
            ],
        ]);
    }

    /**
     * GET /api/wallet/transactions
     * auth:sanctum + role:encherisseur (validé)
     * Historique paginé des transactions du wallet.
     */
    public function transactions(Request $request)
    {
        $user = $request->user()->load('encherisseur');

        if (!$user->encherisseur) {
            return response()->json(['message' => 'Profil enchérisseur introuvable.'], 403);
        }

        $transactions = Transaction::where('encherisseur_id', $user->encherisseur->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json($transactions);
    }

    /**
     * POST /api/wallet/deposer
     * auth:sanctum + role:encherisseur (validé)
     * Recharger le wallet (simulation opérateur mobile — Orange Money, Moov Money).
     * Body attendu : { "montant": 5000, "mode_paiement": "orange_money", "telephone_paiement": "76123456" }
     */
    public function deposer(Request $request)
    {
        $request->validate([
            'montant'            => 'required|numeric|min:100',
            'mode_paiement'      => 'required|string|max:50',
            'telephone_paiement' => 'required|string|max:191',
        ]);

        $user = $request->user()->load('encherisseur');

        if (!$user->encherisseur) {
            return response()->json(['message' => 'Profil enchérisseur introuvable.'], 403);
        }

        // Générer une référence de transaction simulée
        $reference = 'SIMU-' . now()->format('YmdHis');

        // Appeler le WalletService pour créditer le wallet et créer la transaction
        $transaction = $this->walletService->deposer(
            $user->encherisseur,
            (float) $request->montant,
            $reference
        );

        // Enregistrer l'action dans les logs
        AppLog::create([
            'user_id'     => $user->id,
            'action'      => 'depot_wallet',
            'description' => "Dépôt de {$request->montant} FCFA via {$request->mode_paiement}",
            'horodatage'  => now(),
        ]);

        // Recharger le wallet pour avoir le nouveau solde
        $nouveauSolde = (float) $user->encherisseur->wallet->fresh()->solde;

        return response()->json([
            'message' => 'Recharge effectuée avec succès.',
            'data'    => [
                'solde'       => $nouveauSolde,
                'transaction' => $transaction,
            ],
        ]);
    }

    /**
     * GET /api/mes-cautions
     * auth:sanctum + role:encherisseur (validé)
     * Montant total des cautions actuellement bloquées (sessions non terminées).
     */
    public function cautions(Request $request)
    {
        $user = $request->user()->load('encherisseur');

        if (!$user->encherisseur) {
            return response()->json(['message' => 'Profil enchérisseur introuvable.'], 403);
        }

        // On additionne les cautions prélevées pour les sessions encore actives
        $totalCautions = Transaction::where('encherisseur_id', $user->encherisseur->id)
            ->where('type', 'prelevement_caution')
            ->whereHas('session', function ($q) {
                // Sessions pas encore terminées = cautions toujours bloquées
                $q->whereIn('statut', ['a_venir', 'en_cours', 'interrompue']);
            })
            ->sum('montant');

        return response()->json([
            'data' => [
                // abs() pour s'assurer que le montant est positif
                'total_cautions_bloquees' => (float) abs($totalCautions),
            ],
        ]);
    }
}
