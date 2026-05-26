<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\Offre;
use App\Models\SessionEnchere;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class AdjudicationService
{
    public function __construct(
        private WalletService $walletService,
        private LogService $logService
    ) {}

    /**
     * Clôture une session et détermine le gagnant.
     * Retourne l'offre gagnante ou null si aucune offre valide.
     */
    public function cloturer(SessionEnchere $session): ?Offre
    {
        return DB::transaction(function () use ($session) {
            // Marquer la session comme terminée
            $session->update(['statut' => SessionEnchere::STATUT_TERMINEE]);

            // Meilleure offre (la plus haute)
            $offres = $session->offres()->orderByDesc('montant')->get();

            // Vérifier le prix de réserve
            $prixReserve = $session->prix_reserve ?? 0;
            $offreGagnante = $offres->first();

            if (!$offreGagnante || $offreGagnante->montant < $prixReserve) {
                // Aucune offre acceptable
                $session->bien->update(['statut' => 'publie']); // Remis en vente
                $this->logService->enregistrer(
                    null,
                    'adjudication',
                    "Aucune offre valide. Session #{$session->id} annulée, bien remis en vente."
                );
                return null;
            }

            // Désigner le gagnant
            $offreGagnante->update(['est_gagnante' => true, 'rang' => 1]);

            // Déterminer le rang des autres offres (pour le report en cas de défaillance)
            foreach ($offres->skip(1) as $index => $offre) {
                $offre->update(['rang' => $index + 2]);
            }

            $session->update(['offre_gagnante_id' => $offreGagnante->id]);

            // Rembourser les cautions des perdants
            $caution = $session->getCautionRequise();
            $perdants = $session->offres()
                ->where('est_gagnante', false)
                ->with('encherisseur')
                ->get();

            foreach ($perdants as $offre) {
                $this->walletService->rembourser($offre->encherisseur, $caution, $session->id);
            }

            $this->logService->enregistrer(
                null,
                'adjudication',
                "Session #{$session->id} clôturée. Gagnant : {$offreGagnante->id_anonyme}"
            );

            return $offreGagnante;
        });
    }
}
