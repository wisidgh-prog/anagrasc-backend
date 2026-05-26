<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\SessionEnchere;
use Illuminate\Http\Request;
use App\Models\Bien;
use App\Http\Controllers\Api\LogController;
class IncidentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Superviseur – Signalement d'un incident
    |--------------------------------------------------------------------------
    */

    /**
     * POST /api/incidents
     * auth:sanctum + role:superviseur
     * Signaler un incident (CDC Scénario 5)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'session_id'     => 'required|exists:sessions_enchere,id',
            'type_incident'  => 'required|string|max:191',
            'description'    => 'required|string',
            'id_concerne'    => 'nullable|string|max:191', // ID anonyme (ex: ENCH-099)
        ]);

        $incident = Incident::create([
            ...$validated,
            'superviseur_id' => $request->user()->id,
            'statut'         => Incident::STATUT_OUVERT,
            'date_signalement' => now(),
        ]);
        //
        LogController::log(
            'incident_signale',
            "Incident #{$incident->id} signalé sur la session #{$validated['session_id']} : {$validated['type_incident']}"
        );

        return response()->json([
            'message' => 'Incident signalé au commissaire-priseur.',
            'data'    => $incident,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Commissaire – Consultation et décision
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/incidents
     * auth:sanctum + role:commissaire_priseur
     * Liste des incidents (ouverts, traités, clos).
     */
    public function index(Request $request)
    {
        $incidents = Incident::with([
                'session.bien:id,titre',
                'superviseur:id,nom,prenom',
            ])
            ->when($request->statut, fn($q, $s) => $q->where('statut', $s))
            ->orderByDesc('date_signalement')
            ->paginate(15);

        return response()->json($incidents);
    }

    /**
     * POST /api/incidents/{incident}/decision
     * auth:sanctum + role:commissaire_priseur
     * Prendre une décision sur un incident 
     */

public function decision(Request $request, Incident $incident)
{
    if ($incident->statut !== Incident::STATUT_OUVERT) {
        return response()->json(['message' => 'Cet incident a déjà été traité.'], 422);
    }

    $validated = $request->validate([
        'decision'      => 'required|in:maintien,annulation,reprogrammation',
        'note_decision' => 'nullable|string',
    ]);

    $incident->update([
        'statut'          => Incident::STATUT_TRAITE,
        'decision'        => $validated['decision'],
        'note_decision'   => $validated['note_decision'] ?? null,
        'traite_par'      => $request->user()->id,
        'date_traitement' => now(),
    ]);
       LogController::log(
        'decision_incident',
        "Décision '{$validated['decision']}' prise sur l'incident #{$incident->id}"
       );

    $session = $incident->session;
    $bien = $session->bien;

    // Actions selon la décision (CDC Scénario 6)
    switch ($validated['decision']) {
        case Incident::DECISION_MAINTIEN:
            // L'enchère continue normalement, rien à faire
            break;

        case Incident::DECISION_ANNULATION:
            // Arrêt définitif de la session, bien remis en vente
            $session->update(['statut' => SessionEnchere::STATUT_TERMINEE]);
            $bien->update(['statut' => Bien::STATUT_PUBLIE]);
            // Remboursement des cautions serait géré par AdjudicationService si nécessaire
            break;

        case Incident::DECISION_REPROGRAMMATION:
            // Annuler la session actuelle + créer une nouvelle session sur le même bien
            $session->update(['statut' => SessionEnchere::STATUT_TERMINEE]);
            $bien->update(['statut' => Bien::STATUT_PUBLIE]);

            // Création de la nouvelle session avec les mêmes paramètres
            // Les dates sont décalées de 7 jours (par défaut), le commissaire reste le même
            $nouvelleSession = SessionEnchere::create([
                'bien_id'                => $bien->id,
                'commissaire_id'         => $session->commissaire_id, // le même commissaire
                'superviseur_id'         => $session->superviseur_id, // même superviseur
                'date_debut'             => now()->addDays(7),
                'date_fin'               => now()->addDays(7)->addHours(6), // durée de 6h par exemple
                'mise_a_prix_effective'  => $session->mise_a_prix_effective,
                'montant_min_surenchere' => $session->montant_min_surenchere,
                'prix_reserve'           => $session->prix_reserve,
                'statut'                 => SessionEnchere::STATUT_A_VENIR,
            ]);

            // Noter dans la réponse l'ID de la nouvelle session
            $incident -> fresh(); // recharger les relations si besoin
            $incident->note_decision .= "\nNouvelle session créée : ID {$nouvelleSession->id}";
            $incident->save();
            break;
    }

    return response()->json([
        'message' => 'Décision enregistrée.',
        'data'    => $incident->fresh(),
    ]);
}
}
