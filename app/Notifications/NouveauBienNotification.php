<?php

namespace App\Notifications;

use App\Models\Bien;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NouveauBienNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Bien $bien) {}

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
            ->subject('Nouveau bien disponible : ' . $this->bien->titre)
            ->greeting('Bonjour,')
            ->line("Un nouveau bien vient d'être mis aux enchères sur la plateforme ANAGRASC.")
            ->line('Bien : ' . $this->bien->titre)
            ->line('Catégorie : ' . ($this->bien->categorie->libelle ?? '-'));

        if ($this->bien->mise_a_prix) {
            $message->line('Mise à prix : ' . number_format((float) $this->bien->mise_a_prix, 0, ',', ' ') . ' FCFA');
        }

        if ($this->bien->lieu_retrait) {
            $message->line('Lieu de retrait : ' . $this->bien->lieu_retrait);
        }

        if ($this->bien->description) {
            $message->line('Description : ' . $this->bien->description);
        }

        return $message
            ->action('Voir le catalogue', url('/catalogue'))
            ->line('Merci de votre confiance.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
