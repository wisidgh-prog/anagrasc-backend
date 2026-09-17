<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\AppLog;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class BienController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}
    /*
    |--------------------------------------------------------------------------
    | Consultation publique du catalogue
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/biens
     * Public – Liste paginée des biens publiés ou en cours.
     */
    public function index(Request $request)
    {
        // On ne montre que les biens publiés ou en cours d'enchère au public
        $query = Bien::whereIn('statut', [Bien::STATUT_PUBLIE, Bien::STATUT_EN_COURS])
            ->with(['categorie', 'photos' => function ($q) {
                // On ne charge que la première photo (la photo principale)
                $q->orderBy('ordre')->limit(1);
            }])
            ->orderByDesc('created_at');

        // Filtre par mot-clé dans le titre ou la description
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        // Filtre par catégorie
        if ($request->filled('categorie_id')) {
            $query->where('categorie_id', $request->categorie_id);
        }

        // Filtre par statut (seulement publie ou en_cours autorisés)
        if ($request->filled('statut')) {
            if (in_array($request->statut, [Bien::STATUT_PUBLIE, Bien::STATUT_EN_COURS])) {
                $query->where('statut', $request->statut);
            }
        }

        $biens = $query->paginate(12);

        return response()->json($biens);
    }

    /**
     * GET /api/biens/{bien}
     * Public – Détail d'un bien avec ses sessions à venir ou en cours.
     */
    public function show(Bien $bien)
    {
        // Vérifier que le bien est visible publiquement
        if (!in_array($bien->statut, [Bien::STATUT_PUBLIE, Bien::STATUT_EN_COURS])) {
            return response()->json(['message' => 'Bien introuvable.'], 404);
        }

        $bien->load(['categorie', 'photos', 'sessions' => function ($q) {
            // Charger uniquement les sessions à venir ou en cours
            $q->whereIn('statut', ['a_venir', 'en_cours'])->orderBy('date_debut');
        }]);

        return response()->json(['data' => $bien]);
    }

    /*
    |--------------------------------------------------------------------------
    | Gestion des biens par le commissaire-priseur
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/commissaire/biens
     * auth:sanctum + role:commissaire_priseur
     * Liste des biens créés par le commissaire connecté.
     */
    public function mesBiens(Request $request)
    {
        $query = Bien::where('cree_par', $request->user()->id)
            ->with('categorie')
            ->orderByDesc('created_at');

        // Filtre par statut si demandé
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        $biens = $query->paginate(15);

        return response()->json($biens);
    }

    /**
     * POST /api/biens
     * auth:sanctum + role:commissaire_priseur
     * Création d'un bien. Le statut est forcé à "en_attente_validation".
     */
    public function store(Request $request)
    {
        $request->validate([
            'categorie_id'    => 'required|exists:categories,id',
            'titre'           => 'required|string|max:191',
            'description'     => 'required|string',
            'mise_a_prix'     => 'required|numeric|min:0',
            'lieu_retrait'    => 'required|string|max:191',
            'horaires_visite' => 'nullable|string|max:191',
            'remarques'       => 'nullable|string',
            'photos'          => 'nullable|array',
            'photos.*'        => 'image|mimes:jpg,jpeg,png|max:5120',
        ]);

        // Créer le bien avec le statut "en attente de validation"
        $bien = Bien::create([
            'categorie_id'    => $request->categorie_id,
            'titre'           => $request->titre,
            'description'     => $request->description,
            'mise_a_prix'     => $request->mise_a_prix,
            'lieu_retrait'    => $request->lieu_retrait,
            'horaires_visite' => $request->horaires_visite,
            'remarques'       => $request->remarques,
            'cree_par'        => $request->user()->id,
            'statut'          => Bien::STATUT_EN_ATTENTE_VALIDATION,
        ]);

        // Ajouter les photos si elles ont été envoyées
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $index => $photo) {
                $bien->photos()->create([
                    'url'   => $photo->store('biens', 'public'),
                    'ordre' => $index + 1,
                ]);
            }
        }

        // Enregistrer l'action dans les logs
        AppLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'creation_bien',
            'description' => "Bien #{$bien->id} '{$bien->titre}' créé",
            'horodatage'  => now(),
        ]);

        return response()->json([
            'message' => 'Bien créé et soumis pour validation.',
            'data'    => $bien,
        ], 201);
    }

    /**
     * PUT /api/biens/{bien}
     * auth:sanctum + role:commissaire_priseur
     * Modification d'un bien (autorisée seulement si en attente ou rejeté).
     */
    public function update(Request $request, Bien $bien)
    {
        // Seul le créateur du bien ou un admin peut le modifier
        if ($bien->cree_par !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        // On ne peut modifier que les biens en attente de validation ou rejetés
        if (!in_array($bien->statut, [Bien::STATUT_EN_ATTENTE_VALIDATION, Bien::STATUT_REJETE])) {
            return response()->json(['message' => 'Ce bien ne peut plus être modifié.'], 422);
        }

        $request->validate([
            'categorie_id'    => 'sometimes|exists:categories,id',
            'titre'           => 'sometimes|string|max:191',
            'description'     => 'sometimes|string',
            'mise_a_prix'     => 'sometimes|numeric|min:0',
            'lieu_retrait'    => 'sometimes|string|max:191',
            'horaires_visite' => 'nullable|string|max:191',
            'remarques'       => 'nullable|string',
        ]);

        $bien->update([
            'categorie_id'    => $request->categorie_id ?? $bien->categorie_id,
            'titre'           => $request->titre ?? $bien->titre,
            'description'     => $request->description ?? $bien->description,
            'mise_a_prix'     => $request->mise_a_prix ?? $bien->mise_a_prix,
            'lieu_retrait'    => $request->lieu_retrait ?? $bien->lieu_retrait,
            'horaires_visite' => $request->horaires_visite ?? $bien->horaires_visite,
            'remarques'       => $request->remarques ?? $bien->remarques,
        ]);

        // Enregistrer l'action dans les logs
        AppLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'modification_bien',
            'description' => "Bien #{$bien->id} '{$bien->titre}' modifié",
            'horodatage'  => now(),
        ]);

        return response()->json([
            'message' => 'Bien modifié.',
            'data'    => $bien->fresh(['photos', 'categorie']),
        ]);
    }

    /**
     * DELETE /api/biens/{bien}
     * auth:sanctum + role:commissaire_priseur
     * Suppression d'un bien (seulement si aucune session n'y est liée).
     */
    public function destroy(Request $request, Bien $bien)
    {
        // Seul le créateur peut supprimer son bien
        if ($bien->cree_par !== $request->user()->id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        // estSupprimable() vérifie qu'aucune session n'est liée à ce bien
        if (!$bien->estSupprimable()) {
            return response()->json(['message' => 'Ce bien ne peut pas être supprimé.'], 422);
        }

        $titreBien = $bien->titre; // on sauvegarde le titre avant suppression pour le log
        $idBien    = $bien->id;

        $bien->delete();

        // Enregistrer l'action dans les logs
        AppLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'suppression_bien',
            'description' => "Bien #{$idBien} '{$titreBien}' supprimé",
            'horodatage'  => now(),
        ]);

        return response()->json(['message' => 'Bien supprimé.']);
    }

    /**
     * POST /api/biens/{bien}/photos
     * auth:sanctum + role:commissaire_priseur
     * Ajout de photos supplémentaires à un bien existant.
     */
    public function ajouterPhotos(Request $request, Bien $bien)
    {
        // Seul le créateur peut ajouter des photos à son bien
        if ($bien->cree_par !== $request->user()->id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $request->validate([
            'photos'   => 'required|array',
            'photos.*' => 'image|mimes:jpg,jpeg,png|max:5120',
        ]);

        // Récupérer l'ordre le plus élevé existant pour continuer la numérotation
        $ordreActuel = $bien->photos()->max('ordre') ?? 0;

        foreach ($request->file('photos') as $index => $photo) {
            $bien->photos()->create([
                'url'   => $photo->store('biens', 'public'),
                'ordre' => $ordreActuel + $index + 1,
            ]);
        }

        // Enregistrer l'action dans les logs
        AppLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'ajout_photos_bien',
            'description' => "Photos ajoutées au bien #{$bien->id} '{$bien->titre}'",
            'horodatage'  => now(),
        ]);

        return response()->json([
            'message' => 'Photos ajoutées.',
            'data'    => $bien->fresh(['photos']),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Gestion des biens par l'administrateur
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/admin/biens
     * auth:sanctum + role:administrateur
     * Voir tous les biens (avec filtres par statut).
     */
    public function indexAdmin(Request $request)
    {
        $query = Bien::with(['categorie', 'createur:id,nom,prenom'])
            ->orderByDesc('created_at');

        // Filtre par statut si demandé
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        $biens = $query->paginate(15);

        return response()->json($biens);
    }

    /**
     * POST /api/admin/biens/{bien}/valider
     * auth:sanctum + role:administrateur
     * Valider un bien en attente de validation → il devient "publié".
     */
    public function valider(Request $request, Bien $bien)
    {
        if ($bien->statut !== Bien::STATUT_EN_ATTENTE_VALIDATION) {
            return response()->json(['message' => 'Ce bien n\'est pas en attente de validation.'], 422);
        }

        $bien->update(['statut' => Bien::STATUT_PUBLIE]);

        // Informer les enchérisseurs validés de l'arrivée de ce nouveau bien
        $this->notificationService->notifierNouveauBien($bien);

        // Enregistrer l'action dans les logs
        AppLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'validation_bien',
            'description' => "Bien #{$bien->id} '{$bien->titre}' validé et publié",
            'horodatage'  => now(),
        ]);

        return response()->json(['message' => 'Bien validé et publié.']);
    }

    /**
     * POST /api/admin/biens/{bien}/rejeter
     * auth:sanctum + role:administrateur
     * Rejeter un bien en attente de validation (avec motif obligatoire).
     */
    public function rejeter(Request $request, Bien $bien)
    {
        if ($bien->statut !== Bien::STATUT_EN_ATTENTE_VALIDATION) {
            return response()->json(['message' => 'Ce bien ne peut pas être rejeté.'], 422);
        }

        $request->validate([
            'motif' => 'required|string|max:1000',
        ]);

        $bien->update([
            'statut'      => Bien::STATUT_REJETE,
            'motif_rejet' => $request->motif,
            'date_rejet'  => now(),
        ]);

        // Enregistrer l'action dans les logs
        AppLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'rejet_bien',
            'description' => "Bien #{$bien->id} '{$bien->titre}' rejeté. Motif : {$request->motif}",
            'horodatage'  => now(),
        ]);

        return response()->json(['message' => 'Bien rejeté.']);
    }
}
