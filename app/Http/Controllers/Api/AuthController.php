<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{ Encherisseur, Wallet, Role};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Inscription d'un nouvel enchérisseur
    | POST /api/register
    |--------------------------------------------------------------------------
    */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'type_personne'                  => 'required|in:physique,morale',
            'nom'                            => 'required_if:type_personne,physique|string|max:191',
            'prenom'                         => 'required_if:type_personne,physique|string|max:191',
            'date_naissance'                 => 'required_if:type_personne,physique|date',
            'lieu_naissance'                 => 'required_if:type_personne,physique|string|max:191',
            'raison_sociale'                 => 'required_if:type_personne,morale|string|max:191',
            'num_rccm'                       => 'required_if:type_personne,morale|string|max:191',
            'num_ifu'                        => 'nullable|string|max:191',
            'representant_legal_nom'         => 'required_if:type_personne,morale|string|max:191',
            'representant_legal_prenom'      => 'required_if:type_personne,morale|string|max:191',
            'representant_legal_date_naissance'=> 'required_if:type_personne,morale|date',
            'representant_legal_lieu_naissance'=> 'required_if:type_personne,morale|string|max:191',
            'representant_legal_fonction'    => 'required_if:type_personne,morale|string|max:191',
            'telephone'                      => 'required|string|max:191|unique:users,telephone',
            'email'                          => 'nullable|email|max:191|unique:users,email',
            'ville'                          => 'required|string|max:191',
            'type_piece'                     => 'required|in:cnib,passeport,carte_consulaire,carte_aes,permis,rccm',
            'num_piece'                      => 'required|string|max:191',
            'piece_recto'                    => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'piece_verso'                    => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'password'                       => 'required|string|min:8|confirmed',
        ]);

        $role = Role::where('nom', 'encherisseur')->firstOrFail();

        $user = User::create([
            'role_id'       => $role->id,
            'type_personne' => $validated['type_personne'],
            'nom'                           => $validated['nom'] ?? null,
            'prenom'                        => $validated['prenom'] ?? null,
            'date_naissance'                => $validated['date_naissance'] ?? null,
            'lieu_naissance'                => $validated['lieu_naissance'] ?? null,
            'raison_sociale'                => $validated['raison_sociale'] ?? null,
            'num_rccm'                      => $validated['num_rccm'] ?? null,
            'num_ifu'                       => $validated['num_ifu'] ?? null,
            'representant_legal_nom'        => $validated['representant_legal_nom'] ?? null,
            'representant_legal_prenom'     => $validated['representant_legal_prenom'] ?? null,
            'representant_legal_date_naissance' => $validated['representant_legal_date_naissance'] ?? null,
            'representant_legal_lieu_naissance' => $validated['representant_legal_lieu_naissance'] ?? null,
            'representant_legal_fonction'   => $validated['representant_legal_fonction'] ?? null,
            'telephone'                     => $validated['telephone'],
            'email'                         => $validated['email'] ?? null,
            'ville'                         => $validated['ville'],
            'password'                      => Hash::make($validated['password']),
            'type_piece'                    => $validated['type_piece'],
            'num_piece'                     => $validated['num_piece'],
            'piece_recto_url'               => $request->file('piece_recto')->store('pieces', 'local'),
            'piece_verso_url'               => $request->file('piece_verso')->store('pieces', 'local'),
            'est_valide'                    => false,
        ]);

        // Créer le profil enchérisseur et le wallet
        $encherisseur = Encherisseur::create(['personne_id' => $user->id]);
        Wallet::create(['encherisseur_id' => $encherisseur->id]);

        return response()->json([
            'message' => 'Inscription réussie. Votre compte est en attente de validation.',
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Connexion
    | POST /api/login
    |--------------------------------------------------------------------------
    */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'telephone' => 'required|string',
            'password'  => 'required|string',
        ]);

        /** @var User|null $user */
        $user = User::where('telephone', $validated['telephone'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Identifiants incorrects.'], 401);
        }

        if ($user instanceof User && $user->isEncherisseur() && !$user->peutEncherir()) {
            return response()->json([
                'message' => 'Votre compte enchérisseur est en attente de validation ou suspendu.',
            ], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'          => $user->id,
                'nom_complet' => $user->nom_complet,
                'role'        => $user->role->nom,
                'telephone'   => $user->telephone,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Profil de l'utilisateur connecté
    | GET /api/me (auth:sanctum)
    |--------------------------------------------------------------------------
    */
    public function me(Request $request)
    {
        $user = $request->user()->load('role', 'encherisseur.wallet');

        return response()->json([
            'data' => [
                'id'          => $user->id,
                'nom_complet' => $user->nom_complet,
                'role'        => $user->role->nom,
                'telephone'   => $user->telephone,
                'email'       => $user->email,
                'est_valide'  => $user->est_valide,
                'solde'       => optional($user->encherisseur)->solde ?? 0,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Déconnexion
    | POST /api/logout (auth:sanctum)
    |--------------------------------------------------------------------------
    */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }
}
