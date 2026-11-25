<?php

namespace App\Services;

use App\Models\Encherisseur;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Déposer un montant sur le wallet de l'enchérisseur.
     */
    public function deposer(Encherisseur $encherisseur, float $montant, ?string $reference = null): Transaction
    {
        return DB::transaction(function () use ($encherisseur, $montant, $reference) {
            $wallet = $encherisseur->wallet;
            $wallet->increment('solde', $montant);

            return Transaction::create([
                'encherisseur_id'  => $encherisseur->id,
                'type'             => Transaction::TYPE_DEPOT,
                'montant'          => $montant,
                'statut'           => 'ok',
                'reference_externe'=> $reference,
                'description'      => 'Recharge du wallet',
            ]);
        });
    }

    /**
     * Débiter un montant (ex: caution, paiement final).
     */
    public function debiter(Encherisseur $e, float $montant, string $type, ?int $sessionId = null): Transaction
{
    return DB::transaction(function () use ($e, $montant, $type, $sessionId) {
        $wallet = Wallet::where('encherisseur_id', $e->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($wallet->solde < $montant) {
            throw new \Exception("Solde insuffisant. Disponible: {$wallet->solde} FCFA, requis: {$montant} FCFA");
        }

        $wallet->decrement('solde', $montant);
        $wallet->update(['date_derniere_operation' => now()]);

        return Transaction::create([
            'encherisseur_id' => $e->id,
            'session_id'      => $sessionId,
            'type'            => $type,
            'montant'         => $montant,
            'statut'          => 'ok',
            'description'     => match($type) {
                'prelevement_caution' => 'Caution prélevée',
                'paiement_final'      => 'Paiement final',
                'penalite'            => 'Pénalité',
                default               => 'Débit',
            },
        ]);
    });
}

    /**
     * Rembourser la caution au perdant.
     *
     * Idempotent : une caution n'est créditée qu'une seule fois par session.
     * Si la session est rejouée (double clôture, incident après clôture), on
     * renvoie la transaction déjà existante sans recréditer le wallet.
     */
    public function rembourser(Encherisseur $encherisseur, float $montant, ?int $sessionId = null): Transaction
    {
        return DB::transaction(function () use ($encherisseur, $montant, $sessionId) {
            $wallet = Wallet::where('encherisseur_id', $encherisseur->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sessionId !== null) {
                $existant = Transaction::where('encherisseur_id', $encherisseur->id)
                    ->where('session_id', $sessionId)
                    ->where('type', Transaction::TYPE_REMBOURSEMENT_CAUTION)
                    ->first();

                if ($existant) {
                    return $existant;
                }
            }

            $wallet->increment('solde', $montant);
            $wallet->update(['date_derniere_operation' => now()]);

            return Transaction::create([
                'encherisseur_id' => $encherisseur->id,
                'session_id'      => $sessionId,
                'type'            => Transaction::TYPE_REMBOURSEMENT_CAUTION,
                'montant'         => $montant,
                'statut'          => 'ok',
                'description'     => 'Remboursement caution',
            ]);
        });
    }
}
