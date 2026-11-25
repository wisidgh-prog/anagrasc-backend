<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\NotificationSysteme;

/**
 * Centralise l'envoi des notifications internes (in-app).
 *
 * Une notification interne = une ligne dans la table `notifications`
 * (canal 'database' de Laravel), consultable par l'acteur concerné via
 * la cloche de la navbar. Chaque notification porte un `lien` (route
 * frontend) vers lequel l'utilisateur est redirigé au clic.
 */
class NotificationInAppService
{
    /**
     * Envoie une notification à un utilisateur.
     */
    public function envoyer(
        ?User $user,
        string $titre,
        string $message,
        ?string $lien = null,
        string $icone = 'notifications',
        string $categorie = 'info',
    ): void {
        if (!$user) {
            return;
        }

        $user->notify(new NotificationSysteme($titre, $message, $lien, $icone, $categorie));
    }

    /**
     * Envoie la même notification à plusieurs utilisateurs.
     *
     * @param iterable<User|null> $users
     */
    public function envoyerPlusieurs(
        iterable $users,
        string $titre,
        string $message,
        ?string $lien = null,
        string $icone = 'notifications',
        string $categorie = 'info',
    ): void {
        foreach ($users as $user) {
            $this->envoyer($user, $titre, $message, $lien, $icone, $categorie);
        }
    }

    /**
     * Envoie une notification à tous les administrateurs actifs.
     */
    public function auxAdmins(
        string $titre,
        string $message,
        ?string $lien = null,
        string $icone = 'notifications',
        string $categorie = 'info',
    ): void {
        $admins = User::whereHas('role', fn ($q) => $q->where('nom', 'administrateur'))
            ->where('est_suspendu', false)
            ->get();

        $this->envoyerPlusieurs($admins, $titre, $message, $lien, $icone, $categorie);
    }
}
