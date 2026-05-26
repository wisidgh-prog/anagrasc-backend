<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Http\Controllers\Api\LogController;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * GET /api/utilisateurs
     * auth:sanctum + role:administrateur
     * Liste paginée et filtrable de tous les utilisateurs.
     */
    public function utilisateurs(Request $request)
    {
        // On commence la requête
        $query = User::with('role')->orderByDesc('created_at');

        // Filtre par rôle si l'admin l'a demandé (?role=encherisseur)
        if ($request->filled('role')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('nom', $request->role);
            });
        }

        // Filtre par statut de validation (?est_valide=true ou ?est_valide=false)
        if ($request->has('est_valide')) {
            $query->where('est_valide', $request->boolean('est_valide'));
        }

        // Filtre par recherche texte (?search=dupont)
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', '%' . $search . '%')
                  ->orWhere('prenom', 'like', '%' . $search . '%')
                  ->orWhere('telephone', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $users = $query->paginate(20);

        return response()->json($users);
    }

    /**
     * POST /api/utilisateurs/{user}/valider
     * auth:sanctum + role:administrateur
     * Valider un compte enchérisseur après vérification des pièces.
     */
    public function validerCompte(Request $request, User $user)
    {
        // Vérifier que le compte n'est pas déjà validé
        if ($user->est_valide) {
            return response()->json(['message' => 'Ce compte est déjà validé.'], 422);
        }

        // Valider le compte
        $user->update([
            'est_valide' => true,
            'est_suspendu' => false,
            'valide_le'  => now(),
            'valide_par' => $request->user()->id,
        ]);

        //enregister la validation dans les logs
       LogController::log(
        'validation_compte',
        "Compte #{$user->id} ({$user->telephone}) validé"
    );
        return response()->json(['message' => 'Compte validé avec succès.']);
    }

    /**
     * POST /api/utilisateurs/{user}/rejeter
     * auth:sanctum + role:administrateur
     * Rejeter un compte enchérisseur (avec motif).
     */
    public function rejeterCompte(Request $request, User $user)
    {
       $validated =  $request->validate([
            'motif' => 'required|string|max:255',
        ]);

        // On ne peut pas rejeter un compte déjà validé
        if ($user->est_valide) {
            return response()->json(['message' => 'Ce compte est déjà validé.'], 422);
        }

        // On supprime le compte rejeté
        $user->delete();

        //enregister dans les logs
       // Log
    LogController::log(
        'rejet_compte',
        "Compte #{$user->id} ({$user->telephone}) rejeté. Motif : {$validated['motif']}"
    );

        return response()->json([
            'message' => 'Compte rejeté et supprimé'
        ]);



    }

    /**
     * POST /api/utilisateurs/{user}/suspendre
     * auth:sanctum + role:administrateur
     * Suspendre un compte (le rend inactif sans le supprimer).
     */
    public function suspendreCompte(Request $request, User $user)
    {
       $validated = $request->validate(['motif' => 'required|string|max:255']);

        // On ne peut pas suspendre un administrateur
        if ($user->isAdmin()) {
            return response()->json(['message' => 'Impossible de suspendre un administrateur.'], 422);
        }

        // Suspendre le compte
        $user->update([
            'est_suspendu' => true,
            'est_valide'   => false,
        ]);

        // Log
     LogController::log(
        'suspension_compte',
        "Compte #{$user->id} ({$user->telephone}) suspendu. Motif : {$validated['motif']}"
    );

        return response()->json([
            'message' => 'Compte suspendu '
        ]);

    }

    /**
     * POST /api/utilisateurs/interne
     * auth:sanctum + role:administrateur
     * Créer un compte pour un agent interne (commissaire, superviseur, admin).
     */
    public function creerCompteInterne(Request $request)
    {
        $validated = $request->validate([
            'nom'       => 'required|string|max:191',
            'prenom'    => 'required|string|max:191',
            'telephone' => 'required|string|max:191|unique:users,telephone',
            'email'     => 'nullable|email|max:191|unique:users,email',
            'role'      => 'required|in:administrateur,commissaire_priseur,superviseur',
            'password'  => 'required|string|min:8',
        ]);

        // Récupérer le rôle correspondant dans la base de données
        $role = Role::where('nom', $validated['role'])->firstOrFail();

        // Créer le compte interne (déjà validé par défaut)
        $user = User::create([
            'role_id'       => $role->id,
            'type_personne' => null,
            'nom'           => $validated['nom'],
            'prenom'        => $validated['prenom'],
            'telephone'     => $validated['telephone'],
            'email'         => $validated['email'],
            'password'      => bcrypt($validated['password']),
            'est_valide'    => true,
        ]);

        // Log
        LogController::log(
        'creation_compte_interne',
        "Compte interne créé : {$validated['prenom']} {$validated['nom']} (rôle : {$validated['role']})"
    );


        return response()->json([
            'message' => 'Compte interne créé.',
            'data'    => $user->load('role'),
        ], 201);
    }

    /**
     * GET /api/superviseurs
     * auth:sanctum + role:commissaire_priseur OU administrateur
     * Retourne la liste des superviseurs actifs.
     */
    public function superviseurs(Request $request)
    {
        $superviseurs = User::whereHas('role', function ($query) {
                            $query->where('nom', 'superviseur');
                        })
                        ->where('est_valide', true)
                        ->where('est_suspendu', false)
                        ->select('id', 'nom', 'prenom', 'telephone')
                        ->get();

        return response()->json(['data' => $superviseurs]);
    }
}
