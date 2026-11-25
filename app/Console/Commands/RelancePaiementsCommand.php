<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SessionEnchere;
use App\Models\Transaction;
use App\Models\Configuration;
use App\Services\WalletService;
use App\Services\LogService;

class RelancePaiementsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'encheres:relancer-paiements';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Traite les defaillance de paiement des gagnants';

    /**
     * Execute the console command.
     */
    public function __construct(
        private WalletService $wallet,
        private LogService $log
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $delai = (int) Configuration::valeur('delai_paiement_gagnant', 48);

        // Chercher les sessions terminées sans paiement confirmé et dont le délai est dépassé
        $sessions = SessionEnchere::where('statut', SessionEnchere::STATUT_TERMINEE)
            ->whereNotNull('offre_gagnante_id')
            ->whereNull('paiement_effectue_le')
            ->with('offreGagnante.encherisseur.wallet')
            ->get()
            ->filter(fn($s) => $s->date_fin->addHours($delai)->isPast());

        foreach ($sessions as $session) {
            $offre = $session->offreGagnante;
            $gagnant = $offre->encherisseur;

            // Défaillance : la caution est perdue (pénalité)
            Transaction::create([
                'encherisseur_id' => $gagnant->id,
                'session_id'      => $session->id,
                'type'            => Transaction::TYPE_PENALITE,
                'montant'         => -$session->getCautionRequise(),
                'statut'          => 'ok',
                'description'     => 'Caution perdue - défaillance paiement',
            ]);

            // Proposer au second si offre >= prix de réserve.
            // On exclut les enchérisseurs déjà pénalisés sur cette session, sinon un
            // gagnant défaillant (dont on vient de retirer le drapeau 'est_gagnante')
            // serait re-proposé indéfiniment.
            $dejaPenalises = Transaction::where('session_id', $session->id)
                ->where('type', Transaction::TYPE_PENALITE)
                ->pluck('encherisseur_id');

            $second = \App\Models\Offre::where('session_id', $session->id)
                ->where('est_gagnante', false)
                ->whereNotIn('encherisseur_id', $dejaPenalises)
                ->where('montant', '>=', $session->prix_reserve ?? 0)
                ->orderByDesc('montant')
                ->first();

            // Le gagnant défaillant n'est plus l'offre retenue : on retire son
            // marquage, sinon deux offres resteraient 'est_gagnante' (le compte
            // rendu, qui se base sur ce drapeau, désignerait le mauvais gagnant).
            $offre->update(['est_gagnante' => false, 'rang' => null]);

            if ($second) {
                $second->update(['est_gagnante' => true, 'rang' => 1]);
                $session->update(['offre_gagnante_id' => $second->id]);

                $this->log->enregistrer(null, 'report_gagnant', "Report au second - session #{$session->id}");
            } else {
                $session->update(['offre_gagnante_id' => null]);
                $session->bien->update(['statut' => 'publie']);
                $this->log->enregistrer(null, 'remise_en_vente', "Bien remis en vente - session #{$session->id}");
            }
        }

        $this->info(count($sessions) . ' défaillance(s) traitée(s).');
    }
}
