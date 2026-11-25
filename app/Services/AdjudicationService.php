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
        private LogService $logService,
        private NotificationService $notificationService,
        private NotificationInAppService $notifications
    ) {}

    /**
     * Clôture immédiatement toutes les sessions expirées encore marquées
     * "en cours". Permet de fiabiliser l'affichage (historique, live, participations)
     * même si le scheduler (cron) n'a pas tourné à l'heure exacte.
     */
    public function cloturerExpirees(): int
    {
        $expirees = SessionEnchere::where('statut', SessionEnchere::STATUT_EN_COURS)
            ->where('date_fin', '<=', now())
            ->get();

        foreach ($expirees as $session) {
            $this->cloturer($session);
        }

        return $expirees->count();
    }

    /**
     * Clôture une session et détermine le gagnant.
     * Retourne l'offre gagnante ou null si aucune offre valide.
     */
    public function cloturer(SessionEnchere $session): ?Offre
    {
        return DB::transaction(function () use ($session) {
            // Verrou + idempotence : une session déjà clôturée ne doit pas être
            // clôturée une seconde fois (sinon cautions recréditées, rangs écrasés).
            $session = SessionEnchere::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($session->statut === SessionEnchere::STATUT_TERMINEE) {
                return $session->offreGagnante;
            }

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

                // Rembourser la caution à chaque participant (dédupliqué par enchérisseur)
                $caution = $session->getCautionRequise();
                $encherisseurs = $session->offres()
                    ->with('encherisseur')
                    ->get()
                    ->pluck('encherisseur')
                    ->filter()
                    ->unique('id');

                foreach ($encherisseurs as $encherisseur) {
                    $this->walletService->rembourser($encherisseur, $caution, $session->id);
                }

                $this->logService->enregistrer(
                    null,
                    'adjudication',
                    "Aucune offre valide. Session #{$session->id} annulée, bien remis en vente. " .
                        "Cautions remboursées à {$encherisseurs->count()} participant(s)."
                );

                // Notifier les participants (aucun gagnant → mail "non retenu")
                $this->notificationService->notifierResultatEnchere($session, null);

                // Notifications in-app
                $this->notifications->envoyer(
                    $session->commissaire,
                    'Session clôturée sans adjudication',
                    "La session #{$session->id} s'est terminée sans offre valide. Le bien a été remis en vente.",
                    '/commissaire/sessions',
                    'gavel',
                    'enchere'
                );
                $this->notifications->envoyerPlusieurs(
                    $encherisseurs->map->personne->filter(),
                    'Enchère terminée',
                    "La session #{$session->id} s'est terminée sans adjudication.",
                    '/mon-espace/participations',
                    'campaign',
                    'enchere'
                );

                return null;
            }

            // Désigner le gagnant
            $offreGagnante->update(['est_gagnante' => true, 'rang' => 1]);

            // Déterminer le rang des autres offres (pour le report en cas de défaillance)
            // array_values : skip() conserve les clés d'origine, il faut réindexer pour
            // que $index reparte de 0 et que les rangs soient continus (1 = gagnant).
            foreach ($offres->skip(1)->values() as $index => $offre) {
                $offre->update(['rang' => $index + 2]);
            }

            $session->update(['offre_gagnante_id' => $offreGagnante->id]);

            // Rembourser les cautions des perdants (une seule fois par enchérisseur,
            // même s'il a déposé plusieurs offres sur la session)
            $caution = $session->getCautionRequise();
            $perdants = $session->offres()
                ->where('est_gagnante', false)
                ->with('encherisseur')
                ->get()
                ->pluck('encherisseur')
                ->filter()
                ->unique('id');

            foreach ($perdants as $encherisseur) {
                $this->walletService->rembourser($encherisseur, $caution, $session->id);
            }

            $this->logService->enregistrer(
                null,
                'adjudication',
                "Session #{$session->id} clôturée. Gagnant : {$offreGagnante->id_anonyme}"
            );

            // Notifier les participants (gagnant + perdants) après la clôture
            $this->notificationService->notifierResultatEnchere($session, $offreGagnante);

            // Notifications in-app
            $gagnantUser = $offreGagnante->encherisseur?->personne;
            if ($gagnantUser) {
                $this->notifications->envoyer(
                    $gagnantUser,
                    'Vous avez remporté l’enchère',
                    "Félicitations ! Votre offre de {$offreGagnante->montant} FCFA remporte la session #{$session->id}. Procédez au paiement final.",
                    '/mon-espace/participations',
                    'emoji_events',
                    'enchere'
                );
            }
            $this->notifications->envoyerPlusieurs(
                $perdants->map->personne->filter(),
                'Enchère terminée',
                "La session #{$session->id} est terminée. Votre caution a été remboursée.",
                '/mon-espace/participations',
                'campaign',
                'enchere'
            );
            $this->notifications->envoyer(
                $session->commissaire,
                'Enchère clôturée',
                "La session #{$session->id} est clôturée. Gagnant : {$offreGagnante->id_anonyme}.",
                '/commissaire/sessions',
                'gavel',
                'enchere'
            );

            return $offreGagnante;
        });
    }
}
