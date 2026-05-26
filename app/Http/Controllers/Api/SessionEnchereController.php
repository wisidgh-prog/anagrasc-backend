<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SessionEnchere;
use App\Models\Bien;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\LogController;
class SessionEnchereController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Consultation publique
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/ventes-passees
     * Public – Historique des enchères terminées (paginé, filtrable).
     */
    public function historique(Request $request)
    {
        $sessions = SessionEnchere::where('statut', SessionEnchere::STATUT_TERMINEE)
            ->with([
                'bien' => fn($q) => $q->select('id', 'titre', 'categorie_id'),
                'bien.categorie:id,libelle',
                'offreGagnante' => fn($q) => $q->select('id', 'session_id', 'montant', 'id_anonyme'),
            ])
            ->when($request->categorie_id, fn($q, $id) =>
                $q->whereHas('bien', fn($b) => $b->where('categorie_id', $id))
            )
            ->when($request->date_debut, fn($q, $d) => $q->whereDate('date_fin', '>=', $d))
            ->when($request->date_fin, fn($q, $d) => $q->whereDate('date_fin', '<=', $d))
            ->orderByDesc('date_fin')
            ->paginate(15);

        return response()->json($sessions);
    }

    /**
     * GET /api/sessions/{session}
     * Public – Détail d'une session (timer, meilleure offre, anonymat).
     * Le prix de réserve est automatiquement masqué par le modèle.
     */
    public function show(SessionEnchere $session)
    {
        $session->load([
            'bien' => fn($q) => $q->with('categorie', 'photos'),
            'offres' => fn($q) => $q->orderByDesc('montant')->limit(20),
        ]);

        return response()->json([
            'data' => [
                'id'                      => $session->id,
                'bien'                    => $session->bien,
                'statut'                  => $session->statut,
                'date_debut'              => $session->date_debut,
                'date_fin'                => $session->date_fin,
                'mise_a_prix_effective'   => $session->mise_a_prix_effective,
                'montant_min_surenchere'  => $session->montant_min_surenchere,
                'meilleure_offre'         => $session->meilleure_offre?->montant ?? 0,
                'id_meilleur'             => $session->meilleure_offre?->id_anonyme ?? null,
                'secondes_restantes'      => $session->secondes_restantes,
                'offres'                  => $session->offres->map(fn($o) => [
                    'montant'    => $o->montant,
                    'id_anonyme' => $o->id_anonyme,
                    'heure'      => $o->created_at->format('H:i:s'),
                ]),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Commissaire-priseur
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/commissaire/sessions
     * auth:sanctum + role:commissaire_priseur
     * Liste des sessions créées par le commissaire connecté.
     */
    public function indexCommissaire(Request $request)
    {
        $sessions = SessionEnchere::where('commissaire_id', $request->user()->id)
            ->with('bien:id,titre')
            ->orderByDesc('date_debut')
            ->paginate(10);

        return response()->json($sessions);
    }

    /**
     * POST /api/sessions
     * auth:sanctum + role:commissaire_priseur
     * Création d'une session d'enchère.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bien_id'                 => 'required|exists:biens,id',
            'superviseur_id'          => 'nullable|exists:users,id',
            'date_debut'              => 'required|date|after:now',
            'date_fin'                => 'required|date|after:date_debut',
            'mise_a_prix_effective'   => 'required|numeric|min:0',
            'montant_min_surenchere'  => 'required|numeric|min:1',
            'prix_reserve'            => 'nullable|numeric|min:0',
        ]);

        // Vérifier que le bien est publié et appartient au commissaire
        $bien = Bien::where('id', $validated['bien_id'])
            ->where('cree_par', $request->user()->id)
            ->where('statut', Bien::STATUT_PUBLIE)
            ->first();

        if (!$bien) {
            return response()->json(['message' => 'Bien invalide ou déjà en vente.'], 422);
        }

        $session = SessionEnchere::create([
            ...$validated,
            'commissaire_id' => $request->user()->id,
            'statut'         => SessionEnchere::STATUT_A_VENIR,
        ]);
        //log de création de session d'enchère
         LogController::log(
          'creation_session',
          "Session #{$session->id} créée pour le bien #{$request->bien_id}"
);
        return response()->json([
            'message' => 'Session créée.',
            'data'    => $session->load('bien'),
        ], 201);
    }

    /**
     * PUT /api/sessions/{session}
     * auth:sanctum + role:commissaire_priseur
     * Modification d'une session avant son démarrage.
     */
    public function update(Request $request, SessionEnchere $session)
    {
        if ($session->commissaire_id !== $request->user()->id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        if ($session->statut !== SessionEnchere::STATUT_A_VENIR) {
            return response()->json(['message' => 'La session ne peut plus être modifiée.'], 422);
        }

        $validated = $request->validate([
            'superviseur_id'          => 'nullable|exists:users,id',
            'date_debut'              => 'sometimes|date|after:now',
            'date_fin'                => 'sometimes|date|after:date_debut',
            'mise_a_prix_effective'   => 'sometimes|numeric|min:0',
            'montant_min_surenchere'  => 'sometimes|numeric|min:1',
            'prix_reserve'            => 'nullable|numeric|min:0',
        ]);

        $session->update($validated);
        //log de modification de session d'enchère
         LogController::log(
          'modification_session',
          "Session #{$session->id} modifiée"
             );

        return response()->json([
            'message' => 'Session modifiée.',
            'data'    => $session->fresh(),
        ]);
    }

    /**
     * POST /api/sessions/{session}/interrompre
     * auth:sanctum + role:commissaire_priseur
     * Interrompt temporairement une enchère en cours.
     */
    public function interrompre(SessionEnchere $session)
    {
        if ($session->statut !== SessionEnchere::STATUT_EN_COURS) {
            return response()->json(['message' => 'La session n\'est pas en cours.'], 422);
        }

        $session->update(['statut' => SessionEnchere::STATUT_INTERROMPUE]);
        //log d'interruption de session d'enchère
         LogController::log(
          'interruption_session',
          "Session #{$session->id} interrompue"
             );
        return response()->json(['message' => 'Enchère suspendue.']);
    }

    /**
     * POST /api/sessions/{session}/reprendre
     * auth:sanctum + role:commissaire_priseur
     * Reprend une enchère interrompue.
     */
    public function reprendre(SessionEnchere $session)
    {
        if ($session->statut !== SessionEnchere::STATUT_INTERROMPUE) {
            return response()->json(['message' => 'La session n\'est pas interrompue.'], 422);
        }

        $session->update(['statut' => SessionEnchere::STATUT_EN_COURS]);
        //log de reprise de session d'enchère
         LogController::log(
          'reprise_session',
          "Session #{$session->id} reprise"
             );
        return response()->json(['message' => 'Enchère reprise.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Superviseur
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/superviseur/sessions
     * auth:sanctum + role:superviseur
     * Liste des sessions affectées au superviseur connecté.
     */
    public function indexSuperviseur(Request $request)
    {
        $sessions = SessionEnchere::where('superviseur_id', $request->user()->id)
            ->with('bien:id,titre')
            ->orderByDesc('date_debut')
            ->get();

        return response()->json(['data' => $sessions]);
    }

    /**
     * GET /api/superviseur/sessions/{session}
     * auth:sanctum + role:superviseur
     * Temps réel pour supervision (polling) : meilleure offre, offres récentes.
     */
    public function superviser(SessionEnchere $session)
    {
        $session->load([
            'bien:id,titre,categorie_id',
            'bien.categorie:id,libelle',
            'offres' => fn($q) => $q->orderByDesc('montant')->limit(30),
        ]);

        return response()->json([
            'data' => [
                'session'              => [
                    'id'    => $session->id,
                    'statut'=> $session->statut,
                    'date_fin' => $session->date_fin,
                ],
                'bien'                 => $session->bien,
                'meilleure_offre'      => $session->meilleure_offre?->montant ?? 0,
                'id_meilleur'          => $session->meilleure_offre?->id_anonyme ?? null,
                'secondes_restantes'   => $session->secondes_restantes,
                'historique_offres'    => $session->offres->map(fn($o) => [
                    'montant'    => $o->montant,
                    'id_anonyme' => $o->id_anonyme,
                    'heure'      => $o->created_at->format('H:i:s'),
                ]),
                'participants'         => $session->offres
                    ->groupBy('encherisseur_id')
                    ->map(fn($offres) => [
                        'id_anonyme'     => $offres->first()->id_anonyme,
                        'meilleure_offre'=> $offres->max('montant'),
                    ])
                    ->values(),
            ],
        ]);
    }
}
