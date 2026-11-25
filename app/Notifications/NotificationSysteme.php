<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notification générique interne (canal "database" uniquement).
 *
 * Contrairement aux notifications email, celle-ci est stockée dans la table
 * `notifications` et reste consultable dans l'application : le frontend
 * affiche une cloche avec le compteur de non-lues et permet, au clic,
 * de naviguer vers `lien`.
 */
class NotificationSysteme extends Notification
{
    use Queueable;

    public function __construct(
        public string $titre,
        public string $message,
        public ?string $lien = null,
        public string $icone = 'notifications',
        public string $categorie = 'info',
    ) {}

    /**
     * Diffusion uniquement en base de données (pas d'email ici).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Données stockées (JSON) et renvoyées au frontend.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'titre'     => $this->titre,
            'message'   => $this->message,
            'lien'      => $this->lien,
            'icone'     => $this->icone,
            'categorie' => $this->categorie,
        ];
    }
}
