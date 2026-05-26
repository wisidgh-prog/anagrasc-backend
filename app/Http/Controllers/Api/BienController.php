<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\LogController;
class BienController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Consultation publique du catalogue
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/biens
     * Public – Liste paginée des biens (filtres : search, categorie_id, statut).
     */
    public function index(Request $request)
    {
        $biens = Bien::query()
            ->whereIn('statut', [Bien::STATUT_PUBLIE, Bien::STATUT_EN_COURS])
            ->with(['categorie', 'photos' => function ($q) {
                $q->orderBy('ordre')->limit(1);
            }])
            ->when($request->search, function ($q, $search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            })
            ->when($request->categorie_id, function ($q, $cat) {
                $q->where('categorie_id', $cat);
            })
            ->when($request->statut, function ($q, $statut) {
                if (in_array($statut, [Bien::STATUT_PUBLIE, Bien::STATUT_EN_COURS])) {
                    $q->where('statut', $statut);
                }
            })
            ->orderByDesc('created_at')
            ->paginate(12);

        return response()->json($biens);
    }

    /**
     * GET /api/biens/{bien}
     * Public – Détail d'un bien (avec session à venir ou en cours).
     */
    public function show(Bien $bien)
    {
        if (!in_array($bien->statut, [Bien::STATUT_PUBLIE, Bien::STATUT_EN_COURS])) {
            return response()->json(['message' => 'Bien introuvable.'], 404);
        }

        $bien->load(['categorie', 'photos', 'sessions' => function ($q) {
            $q->whereIn('statut', ['a_venir', 'en_cours'])->orderBy('date_debut');
        }]);

        return response()->json(['data' => $bien]);
    }

    /*
    |--------------------------------------------------------------------------
    | Gestion des biens par le commissaire-priseur
    |--------------------------------------------------------------------------
    */

    /*
     * GET /api/commissaire/biens
     * auth:sanctum + role:commissaire_priseur
     * Liste des biens créés par le commissaire connecté.
     */
    public function mesBiens(Request $request)
    {
        $biens = Bien::where('cree_par', $request->user()->id)
            ->with('categorie')
            ->when($request->statut, function ($q, $statut) {
                $q->where('statut', $statut);
            })
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json($biens);
    }

    /**
     * POST /api/biens
     * auth:sanctum + role:commissaire_priseur
     * Création d'un bien (statut forcé à 'publie').
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'categorie_id'   => 'required|exists:categories,id',
            'titre'          => 'required|string|max:191',
            'description'    => 'required|string',
            'mise_a_prix'    => 'required|numeric|min:0',
            'lieu_retrait'   => 'required|string|max:191',
            'horaires_visite'=> 'nullable|string|max:191',
            'remarques'      => 'nullable|string',
            'photos'         => 'nullable|array',
            'photos.*'       => 'image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $bien = Bien::create([
            ...$validated,
            'cree_par' => $request->user()->id,
            'statut'   => Bien::STATUT_PUBLIE,
        ]);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $index => $photo) {
                $bien->photos()->create([
                    'url'   => $photo->store('biens', 'public'),
                    'ordre' => $index + 1,
                ]);
            }
        }
        //log de création de bien
           LogController::log(
        'creation_bien',
        "Bien #{$bien->id} '{$bien->titre}' créé"
        );
        return response()->json([
            'message' => 'Bien créé et publié.',
            'data'    => $bien->load('photos', 'categorie'),
        ], 201);
    }

    /**
     * PUT /api/biens/{bien}
     * auth:sanctum + role:commissaire_priseur
     * Modification d'un bien (autorisée uniquement si pas en cours ni vendu).
     */
    public function update(Request $request, Bien $bien)
    {
        if ($bien->cree_par !== $request->user()->id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        if (!$bien->estModifiable()) {
            return response()->json(['message' => 'Ce bien ne peut plus être modifié.'], 422);
        }

        $validated = $request->validate([
            'categorie_id'   => 'sometimes|exists:categories,id',
            'titre'          => 'sometimes|string|max:191',
            'description'    => 'sometimes|string',
            'mise_a_prix'    => 'sometimes|numeric|min:0',
            'lieu_retrait'   => 'sometimes|string|max:191',
            'horaires_visite'=> 'nullable|string|max:191',
            'remarques'      => 'nullable|string',
        ]);

        $bien->update($validated);
        //log de modification de bien
         LogController::log(
        'modification_bien',
        "Bien #{$bien->id} '{$bien->titre}' modifié"
    );

        return response()->json([
            'message' => 'Bien modifié.',
            'data'    => $bien->fresh('photos', 'categorie'),
        ]);
    }

    /**
     * DELETE /api/biens/{bien}
     * auth:sanctum + role:commissaire_priseur
     * Suppression d'un bien (si publié et sans session).
     */
    public function destroy(Request $request, Bien $bien)
    {
        if ($bien->cree_par !== $request->user()->id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        if (!$bien->estSupprimable()) {
            return response()->json(['message' => 'Ce bien ne peut pas être supprimé.'], 422);
        }

        $bien->delete();

        //log de suppression de bien
         LogController::log(
        'suppression_bien',
        "Bien #{$bien->id} '{$bien->titre}' supprimé"
    );

        return response()->json(['message' => 'Bien supprimé.']);
    }

    /**
     * POST /api/biens/{bien}/photos
     * auth:sanctum + role:commissaire_priseur
     * Ajout de photos supplémentaires à un bien.
     */
    public function ajouterPhotos(Request $request, Bien $bien)
    {
        if ($bien->cree_par !== $request->user()->id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $request->validate([
            'photos'   => 'required|array',
            'photos.*' => 'image|mimes:jpg,jpeg,png|max:5120',
        ]);

        foreach ($request->file('photos') as $index => $photo) {
            $bien->photos()->create([
                'url'   => $photo->store('biens', 'public'),
                'ordre' => $bien->photos()->max('ordre') + $index + 1,
            ]);
        }

        //log de modification de bien
         LogController::log(
        'ajout_photos_bien',
        "Photos ajoutées au bien #{$bien->id} '{$bien->titre}'"
    );

        return response()->json([
            'message' => 'Photos ajoutées.',
            'data'    => $bien->fresh('photos'),
        ]);
    }
}
