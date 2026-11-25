<?php

namespace App\Notifications;

use App\Models\SessionEnchere;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification informant les participants qu'une enchère a été reportée
 * et qu'une nouvelle session a été programmée pour le même bien.
 */
class SessionReporteeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SessionEnchere $session,
        public SessionEnchere $nouvelleSession,
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
            ->subject('Enchère reportée : ' . ($this->session->bien->titre ?? ''))
            ->greeting('Bonjour,')
            ->line("L'enchère à laquelle vous participiez a été reportée à une date ultérieure.")
            ->line('Bien : ' . ($this->session->bien->titre ?? '-'))
            ->line('Ancienne session : #' . $this->session->id)
            ->line('Nouvelle session : #' . $this->nouvelleSession->id);

        if ($this->nouvelleSession->date_debut) {
            $message->line('Nouvelle date de début : ' . $this->nouvelleSession->date_debut->format('d/m/Y H:i'));
        }

        if ($this->nouvelleSession->date_fin) {
            $message->line('Nouvelle date de fin : ' . $this->nouvelleSession->date_fin->format('d/m/Y H:i'));
        }

        if ($this->noteDecision) {
            $message->line('Motif : ' . $this->noteDecision);
        }

        return $message
            ->line('Vos offres et cautions de la session précédente restent sans effet ; une nouvelle session a été programmée pour le même bien.')
            ->action('Voir le catalogue', url('/catalogue'))
            ->line('Merci de votre confiance.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
