<?php

namespace App\Services;

use App\Models\Bien;
use App\Models\Offre;
use App\Models\SessionEnchere;
use App\Models\User;
use App\Notifications\NouveauBienNotification;
use App\Notifications\ResultatEnchereNotification;
use App\Notifications\SessionAnnuleeNotification;
use App\Notifications\SessionReporteeNotification;
use Illuminate\Notifications\Notification;

/**
 * NotificationService centralise l'envoi des emails de la plateforme.
 *
 * Tous les envois sont volontairement "best effort" : si un email échoue ou si le
 * destinataire n'a pas d'adresse renseignée, on ignore l'erreur (on loggue) afin de
 * ne jamais bloquer le flux métier (enchères, adjudication, décisions d'incidents).
 */
class NotificationService
{
    public function __construct(private LogService $logService) {}

    /**
     * Envoie la notification "nouveau bien publié" à tous les enchérisseurs validés
     * et non suspendus dont l'adresse email est renseignée.
     */
    public function notifierNouveauBien(Bien $bien): void
    {
        $enchereurs = User::whereHas('role', fn ($q) => $q->where('nom', 'encherisseur'))
            ->where('est_valide', true)
            ->where('est_suspendu', false)
            ->whereNotNull('email')
            ->get();

        foreach ($enchereurs as $user) {
            $this->envoie($user, new NouveauBienNotification($bien));
        }

        $this->logService->enregistrer(
            null,
            'notif_emails',
            "Notification 'nouveau bien' envoyée à {$enchereurs->count()} enchérisseur(s) pour le bien #{$bien->id}."
        );
    }

    /**
     * Envoie le résultat de clôture à tous les participants d'une session :
     * - le gagnant reçoit la notification "enchère gagnée" ;
     * - les autres reçoivent la notification "non retenu".
     * Seul le gagnant existe ($offreGagnante non null) s'il y a adjudication.
     */
    public function notifierResultatEnchere(SessionEnchere $session, ?Offre $offreGagnante): void
    {
        // Charger les participants une seule fois (encherisseur => personne => user)
        $offres = $session->offres()->with('encherisseur.personne')->get();

        // Le gagnant (identifié par l'offre est_gagnante)
        $offreGagnante = $offreGagnante
            ?? $offres->firstWhere('est_gagnante', true)
            ?? $session->offreGagnante;

        // Dédupliquer les utilisateurs (chaque enchérisseur n'a qu'un seul compte)
        $vus = [];

        foreach ($offres as $offre) {
            $personne = $offre->encherisseur?->personne;

            // Pas de compte utilisateur → impossible d'envoyer un email
            if (!$personne || !$personne->email) {
                continue;
            }

            // Nombre de vues : conserver une seule notification par utilisateur
            if (in_array($personne->id, $vus)) {
                continue;
            }
            $vus[] = $personne->id;

            $estGagnant = $offreGagnante && $offreGagnante->id === $offre->id;

            $this->envoie(
                $personne,
                new ResultatEnchereNotification(
                    $session,
                    $estGagnant,
                    (float) $offre->montant,
                    $offre->id_anonyme
                )
            );
        }

        $this->logService->enregistrer(
            null,
            'notif_emails',
            "Résultats d'enchère envoyés pour la session #{$session->id} à " . count($vus) . " participant(s)."
        );
    }

    /**
     * Envoie la notification "session annulée" à tous les participants.
     */
    public function notifierAnnulation(SessionEnchere $session, ?string $noteDecision = null): void
    {
        foreach ($this->participantsDeLaSession($session) as $personne) {
            $this->envoie($personne, new SessionAnnuleeNotification($session, $noteDecision));
        }
    }

    /**
     * Envoie la notification "session reportée" à tous les participants,
     * avec les informations de la nouvelle session reprogrammée.
     */
    public function notifierReport(SessionEnchere $session, SessionEnchere $nouvelleSession, ?string $noteDecision = null): void
    {
        foreach ($this->participantsDeLaSession($session) as $personne) {
            $this->envoie($personne, new SessionReporteeNotification($session, $nouvelleSession, $noteDecision));
        }
    }

    /**
     * Liste unique des utilisateurs (personnes) ayant participé à une session.
     * Un participant est un enchérisseur ayant posé au moins une offre.
     */
    private function participantsDeLaSession(SessionEnchere $session): iterable
    {
        $offres = $session->offres()->with('encherisseur.personne')->get();

        return $offres
            ->pluck('encherisseur.personne')
            ->filter()                       // retire les éventuels null
            ->unique('id')
            ->values();
    }

    /**
     * Envoie une notification à un utilisateur si son adresse est renseignée.
     * Les erreurs SMTP sont capturées et loggées (jamais bloquantes).
     */
    private function envoie(User $user, Notification $notification): void
    {
        if (!$user->email) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            $this->logService->enregistrer(
                null,
                'mail_erreur',
                "Échec d'envoi d'email à {$user->email} : {$e->getMessage()}"
            );
        }
    }
}
