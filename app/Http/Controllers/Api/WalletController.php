<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private WalletService $walletService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Consultation du portefeuille
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/wallet
     * auth:sanctum + role:encherisseur + encherisseur.valide
     * Retourne le solde et le nombre de participations en cours.
     */
    public function show(Request $request)
    {
        $user = $request->user()->load('encherisseur.wallet');
        $wallet = $user->encherisseur?->wallet;

        if (!$wallet) {
            return response()->json(['message' => 'Portefeuille introuvable.'], 404);
        }

        return response()->json([
            'data' => [
                'solde'              => (float) $wallet->solde,
                'nb_participations'  => $user->encherisseur->offres()->distinct('session_id')->count('session_id'),
            ],
        ]);
    }

    /**
     * GET /api/wallet/transactions
     * auth:sanctum + role:encherisseur + encherisseur.valide
     * Historique paginé des transactions.
     */
    public function transactions(Request $request)
    {
        $user = $request->user()->load('encherisseur');
        if (!$user->encherisseur) {
            return response()->json(['message' => 'Profil enchérisseur introuvable.'], 403);
        }

        $transactions = Transaction::where('encherisseur_id', $user->encherisseur->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json($transactions);
    }

    /*
    |--------------------------------------------------------------------------
    | Recharge du wallet (simulation opérateur)
    |--------------------------------------------------------------------------
    */

    /**
     * POST /api/wallet/deposer
     * auth:sanctum + role:encherisseur + encherisseur.valide
     * Recharge le wallet via un mode de paiement (simulé).
     * Body : { "montant": 5000, "mode_paiement": "orange_money", "telephone_paiement": "76654321" }
     */
    public function deposer(Request $request)
    {
        $validated = $request->validate([
            'montant'              => 'required|numeric|min:100',
            'mode_paiement'        => 'required|string|max:50',
            'telephone_paiement'   => 'required|string|max:191',
        ]);

        $user = $request->user()->load('encherisseur');
        if (!$user->encherisseur) {
            return response()->json(['message' => 'Profil enchérisseur introuvable.'], 403);
        }

        $transaction = $this->walletService->deposer(
            $user->encherisseur,
            (float) $validated['montant'],
            'SIMU-' . now()->format('YmdHis')
        );

        return response()->json([
            'message' => 'Recharge effectuée avec succès.',
            'data'    => [
                'solde'       => (float) $user->encherisseur->wallet->fresh()->solde,
                'transaction' => $transaction,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Cautions
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/mes-cautions
     * auth:sanctum + role:encherisseur + encherisseur.valide
     * Montant total des cautions actuellement bloquées (prélevées et non remboursées).
     */

public function cautions(Request $request)
{
    $user = $request->user()->load('encherisseur');
    if (!$user->encherisseur) {
        return response()->json(['message' => 'Profil enchérisseur introuvable.'], 403);
    }

    // Somme des cautions prélevées pour les sessions non terminées
    $total = Transaction::where('encherisseur_id', $user->encherisseur->id)
        ->where('type', 'prelevement_caution')
        ->whereHas('session', fn($q) => $q->whereIn('statut', ['a_venir', 'en_cours', 'interrompue']))
        ->sum('montant');

    return response()->json([
        'data' => [
            'total_cautions_bloquees' => (float) abs($total),
        ],
    ]);
}
}
