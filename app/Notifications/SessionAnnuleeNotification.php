<?php

namespace App\Notifications;

use App\Models\SessionEnchere;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification informant les participants de l'annulation d'une session.
 */
class SessionAnnuleeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SessionEnchere $session,
        public ?string $noteDecision = null
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
        $message = (new MailMessage)
            ->subject('Enchère annulée : ' . ($this->session->bien->titre ?? ''))
            ->greeting('Bonjour,')
            ->line("L'enchère à laquelle vous participiez a été annulée.")
            ->line('Bien : ' . ($this->session->bien->titre ?? '-'))
            ->line('Session : #' . $this->session->id);

        if ($this->session->date_fin) {
            $message->line('Date de fin initiale : ' . $this->session->date_fin->format('d/m/Y H:i'));
        }

        if ($this->noteDecision) {
            $message->line('Motif : ' . $this->noteDecision);
        }

        return $message
            ->line('Les cautions bloquées pour cette session ont été remboursées sur votre portefeuille.')
            ->line('Le bien pourra être remis aux enchères ultérieurement.')
            ->action('Voir le catalogue', url('/catalogue'))
            ->line('Merci de votre confiance.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
