<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppLog;
use App\Models\Bien;
use App\Models\Incident;
use App\Models\Offre;
use App\Models\SessionEnchere;
use App\Services\NotificationService;
use App\Services\NotificationInAppService;
use App\Services\WalletService;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
        private WalletService $walletService,
        private NotificationInAppService $notifications
    ) {}

    /**
     * Rembourse la caution à chaque participant distinct d'une session.
     * Utilisé lors d'une annulation ou d'une reprogrammation (le mail informant
     * les participants promet ce remboursement, il doit donc avoir lieu).
     */
    private function rembourserCautions(SessionEnchere $session): void
    {
        $caution = $session->getCautionRequise();

        // Participants = enchérisseurs ayant posé au moins une offre, sans doublon.
        $encherisseurs = Offre::where('session_id', $session->id)
            ->with('encherisseur')
            ->get()
            ->pluck('encherisseur')
            ->filter()
            ->unique('id');

        foreach ($encherisseurs as $encherisseur) {
            $this->walletService->rembourser($encherisseur, $caution, $session->id);
        }

        AppLog::create([
            'user_id'     => auth()->id(),
            'action'      => 'remboursement_cautions',
            'description' => "Cautions remboursées à {$encherisseurs->count()} participant(s) de la session #{$session->id}",
            'horodatage'  => now(),
        ]);
    }
    /**
     * POST /api/incidents
     * auth:sanctum + role:superviseur
     * Signaler un incident sur une session en cours (CDC Scénario 5).
     */
    public function store(Request $request)
    {
        $request->validate([
            'session_id'    => 'required|exists:sessions_enchere,id',
            'type_incident' => 'required|string|max:191',
            'description'   => 'required|string',
            'id_concerne'   => 'nullable|string|max:191', // ID anonyme de l'enchérisseur concerné, ex: ENCH-099
        ]);

        // Le superviseur ne peut signaler que des incidents sur des sessions qui lui sont affectées
        $session = SessionEnchere::findOrFail($request->session_id);

        if ($session->superviseur_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Action non autorisée sur cette session.'], 403);
        }

        // Créer l'incident avec le statut "ouvert"
        $incident = Incident::create([
            'session_id'       => $request->session_id,
            'superviseur_id'   => $request->user()->id,
            'type_incident'    => $request->type_incident,
            'description'      => $request->description,
            'id_concerne'      => $request->id_concerne,
            'statut'           => Incident::STATUT_OUVERT,
            'date_signalement' => now(),
        ]);

        // Enregistrer l'action dans les logs
        AppLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'incident_signale',
            'description' => "Incident #{$incident->id} signalé sur la session #{$request->session_id} : {$request->type_incident}",
            'horodatage'  => now(),
        ]);

        // Notifier le commissaire propriétaire de la session
        $this->notifications->envoyer(
            $session->commissaire,
            'Nouvel incident signalé',
            "Un incident '{$request->type_incident}' a été signalé sur la session #{$session->id}.",
            '/commissaire/incidents',
            'warning',
            'incident'
        );

        return response()->json([
            'message' => 'Incident signalé au commissaire-priseur.',
            'data'    => $incident,
        ], 201);
    }

    /**
     * GET /api/incidents
     * auth:sanctum + role:commissaire_priseur
     * Liste des incidents (filtrables par statut).
     */
    public function index(Request $request)
    {
        $query = Incident::with([
                'session.bien:id,titre',
                'superviseur:id,nom,prenom',
            ])
            ->orderByDesc('date_signalement');

        // Un commissaire ne voit que les incidents liés à ses propres sessions (l'admin voit tout)
        if (!$request->user()->isAdmin()) {
            $query->whereHas('session', fn ($q) => $q->where('commissaire_id', $request->user()->id));
        }

        // Filtre par statut si demandé (?statut=ouvert)
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        $incidents = $query->paginate(10);

        return response()->json($incidents);
    }

    /**
     * POST /api/incidents/{incident}/decision
     * auth:sanctum + role:commissaire_priseur
     * Prendre une décision sur un incident ouvert (CDC Scénario 6).
     *
     * Décisions possibles :
     * - maintien          → l'enchère continue normalement
     * - annulation        → l'enchère est arrêtée définitivement
     * - reprogrammation   → l'enchère est annulée et une nouvelle est créée dans 7 jours
     */
    public function decision(Request $request, Incident $incident)
    {
        // Un commissaire ne peut traiter qu'un incident sur une de ses sessions (l'admin peut tout)
        if (!$request->user()->isAdmin() && $incident->session->commissaire_id !== $request->user()->id) {
            return response()->json(['message' => 'Action non autorisée sur cet incident.'], 403);
        }

        // On ne peut pas traiter un incident déjà traité
        if ($incident->statut !== Incident::STATUT_OUVERT) {
            return response()->json(['message' => 'Cet incident a déjà été traité.'], 422);
        }

        $request->validate([
            'decision'      => 'required|in:maintien,annulation,reprogrammation',
            'note_decision' => 'nullable|string',
        ]);

        // Enregistrer la décision sur l'incident
        $incident->update([
            'statut'          => Incident::STATUT_TRAITE,
            'decision'        => $request->decision,
            'note_decision'   => $request->note_decision,
            'traite_par'      => $request->user()->id,
            'date_traitement' => now(),
        ]);

        // Enregistrer l'action dans les logs
        AppLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'decision_incident',
            'description' => "Décision '{$request->decision}' prise sur l'incident #{$incident->id}",
            'horodatage'  => now(),
        ]);

        // Récupérer la session et le bien liés à cet incident
        $session = $incident->session;
        $bien    = $session->bien;

        // Appliquer la décision selon le cas (CDC Scénario 6)
        if ($request->decision === Incident::DECISION_MAINTIEN) {
            // L'enchère continue normalement, on ne fait rien
        }

        if ($request->decision === Incident::DECISION_ANNULATION) {
            // Arrêt définitif de la session
            $session->update(['statut' => SessionEnchere::STATUT_TERMINEE]);
            // Le bien est remis en vente (redevient "publié")
            $bien->update(['statut' => Bien::STATUT_PUBLIE]);

            // Rembourser les cautions (promis par le mail d'annulation)
            $this->rembourserCautions($session);

            // Informer les participants de l'annulation
            $this->notificationService->notifierAnnulation($session, $request->note_decision);
        }

        if ($request->decision === Incident::DECISION_REPROGRAMMATION) {
            // Annuler la session actuelle
            $session->update(['statut' => SessionEnchere::STATUT_TERMINEE]);
            // Le bien est remis en vente
            $bien->update(['statut' => Bien::STATUT_PUBLIE]);

            // Rembourser les cautions de l'ancienne session
            $this->rembourserCautions($session);

            // Créer une nouvelle session avec les mêmes paramètres, décalée de 7 jours
            $nouvelleSession = SessionEnchere::create([
                'bien_id'                => $bien->id,
                'commissaire_id'         => $session->commissaire_id,
                'superviseur_id'         => $session->superviseur_id,
                'date_debut'             => now()->addDays(7),
                'date_fin'               => now()->addDays(7)->addHours(6),
                'mise_a_prix_effective'  => $session->mise_a_prix_effective,
                'montant_min_surenchere' => $session->montant_min_surenchere,
                'prix_reserve'           => $session->prix_reserve,
                'statut'                 => SessionEnchere::STATUT_A_VENIR,
            ]);

            // Informer les participants du report et de la nouvelle session
            $this->notificationService->notifierReport($session, $nouvelleSession, $request->note_decision);

            // Ajouter l'ID de la nouvelle session dans la note de décision
            $incident->note_decision = ($incident->note_decision ?? '') . "\nNouvelle session créée : ID {$nouvelleSession->id}";
            $incident->save();
        }

        // Notifier le superviseur affecté de la décision prise
        if ($session->superviseur_id) {
            $this->notifications->envoyer(
                $session->superviseur,
                'Décision sur incident',
                "L'incident #{$incident->id} a été traité (décision : {$request->decision}).",
                '/superviseur',
                'gavel',
                'incident'
            );
        }

        return response()->json([
            'message' => 'Décision enregistrée.',
            'data'    => $incident->fresh(),
        ]);
    }
}
