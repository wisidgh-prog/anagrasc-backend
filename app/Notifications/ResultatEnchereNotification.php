<?php

namespace App\Notifications;

use App\Models\SessionEnchere;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification envoyée aux participants à la clôture d'une enchère.
 * Le même message s'adapte au cas (gagnant ou non retenu).
 */
class ResultatEnchereNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SessionEnchere $session,
        public bool $estGagnant,
        public float $montant,
        public ?string $idAnonyme = null
    ) {}

    /**
     * Canaux de diffusion de la notification.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Représentation email de la notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $titre = $this->session->bien->titre ?? '';

        if ($this->estGagnant) {
            return (new MailMessage)
                ->subject("Félicitations, vous avez remporté l'enchère !")
                ->greeting('Bonjour,')
                ->line("Vous avez remporté l'enchère pour le bien : " . $titre)
                ->line('Montant adjugé : ' . number_format($this->montant, 0, ',', ' ') . ' FCFA')
                ->line('Votre identifiant : ' . ($this->idAnonyme ?? '-'))
                ->line("Le règlement du montant adjugé doit intervenir dans le délai prévu par la plateforme.")
                ->line("À défaut, la caution prélevée pourra être perdue et l'enchère sera proposée au participant suivant.")
                ->action('Accéder à mon espace', url('/mon-espace'))
                ->line('Merci de votre confiance.');
        }

        return (new MailMessage)
            ->subject("Résultat de l'enchère : " . $titre)
            ->greeting('Bonjour,')
            ->line("Cette enchère ne vous a malheureusement pas été adjugée.")
            ->line('Bien : ' . $titre)
            ->line('Votre identifiant : ' . ($this->idAnonyme ?? '-'))
            ->line('Votre meilleure offre : ' . number_format($this->montant, 0, ',', ' ') . ' FCFA')
            ->line('Votre caution vous a été remboursée sur votre portefeuille.')
            ->line("De nouvelles enchères sont régulièrement proposées, n'hésitez pas à consulter le catalogue.")
            ->action('Voir le catalogue', url('/catalogue'))
            ->line('Merci de votre confiance.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
