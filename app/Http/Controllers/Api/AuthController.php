<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encherisseur;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
        $request->validate([
            'type_personne'                     => 'required|in:physique,morale',

            // Champs obligatoires uniquement si type_personne = physique
            'nom'                               => 'required_if:type_personne,physique|string|max:191',
            'prenom'                            => 'required_if:type_personne,physique|string|max:191',
            'date_naissance'                    => 'required_if:type_personne,physique|date',
            'lieu_naissance'                    => 'required_if:type_personne,physique|string|max:191',

            // Champs obligatoires uniquement si type_personne = morale
            'raison_sociale'                    => 'required_if:type_personne,morale|string|max:191',
            'num_rccm'                          => 'required_if:type_personne,morale|string|max:191',
            'num_ifu'                           => 'nullable|string|max:191',
            'representant_legal_nom'            => 'required_if:type_personne,morale|string|max:191',
            'representant_legal_prenom'         => 'required_if:type_personne,morale|string|max:191',
            'representant_legal_date_naissance' => 'required_if:type_personne,morale|date',
            'representant_legal_lieu_naissance' => 'required_if:type_personne,morale|string|max:191',
            'representant_legal_fonction'       => 'required_if:type_personne,morale|string|max:191',

            // Champs communs
            'telephone' => 'required|string|max:191|unique:users,telephone',
            'email'     => 'nullable|email|max:191|unique:users,email',
            'ville'     => 'required|string|max:191',
            'type_piece'=> 'required|in:cnib,passeport,carte_consulaire,carte_aes,permis,rccm',
            'num_piece' => 'required|string|max:191',
            'piece_recto' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'piece_verso' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'password'  => 'required|string|min:8|confirmed',
        ]);

        // Le rôle est toujours "encherisseur" pour l'inscription publique
        // On ne laisse JAMAIS l'utilisateur choisir son rôle
        $role = Role::where('nom', 'encherisseur')->firstOrFail();

        // Stocker les pièces d'identité dans le disque "local" (privé, non accessible publiquement)
        $pieceRectoUrl = $request->file('piece_recto')->store('pieces', 'local');
        $pieceVersoUrl = $request->file('piece_verso')->store('pieces', 'local');

        // Créer le compte utilisateur
        $user = new User([
            'type_personne'                     => $request->type_personne,
            'nom'                               => $request->nom,
            'prenom'                            => $request->prenom,
            'date_naissance'                    => $request->date_naissance,
            'lieu_naissance'                    => $request->lieu_naissance,
            'raison_sociale'                    => $request->raison_sociale,
            'num_rccm'                          => $request->num_rccm,
            'num_ifu'                           => $request->num_ifu,
            'representant_legal_nom'            => $request->representant_legal_nom,
            'representant_legal_prenom'         => $request->representant_legal_prenom,
            'representant_legal_date_naissance' => $request->representant_legal_date_naissance,
            'representant_legal_lieu_naissance' => $request->representant_legal_lieu_naissance,
            'representant_legal_fonction'       => $request->representant_legal_fonction,
            'telephone'                         => $request->telephone,
            'email'                             => $request->email,
            'ville'                             => $request->ville,
            'password'                          => Hash::make($request->password),
            'type_piece'                        => $request->type_piece,
            'num_piece'                         => $request->num_piece,
            'piece_recto_url'                   => $pieceRectoUrl,
            'piece_verso_url'                   => $pieceVersoUrl,
        ]);

        // Champs privilégiés définis uniquement côté serveur (jamais assignables en masse)
        $user->forceFill([
            'role_id'                           => $role->id,
            'est_valide'                        => false, // doit être validé par l'admin
        ])->save();

        // Créer le profil enchérisseur lié au compte
        $encherisseur = Encherisseur::create(['personne_id' => $user->id]);

        // Créer le wallet (portefeuille) de l'enchérisseur avec un solde à 0
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
        $request->validate([
            'telephone' => 'nullable|string',
            'identifiant' => 'nullable|string',
            'password'  => 'required|string',
        ]);

        // "identifiant" est accepté comme alias de "telephone" pour la connexion par email.
        // Le champ "telephone" est conservé pour la rétrocompatibilité de l'API.
        $identifiant = $request->identifiant ?? $request->telephone;

        // Pour ne pas que les deux champs soient tous deux absents
        if (blank($identifiant)) {
            return response()->json(['message' => 'Identifiants incorrects.'], 401);
        }

        // Connexion possible par email OU par téléphone (indifférent, insensible à la casse pour l'email)
        $user = User::where('telephone', $identifiant)
            ->orWhereRaw('LOWER(email) = ?', [strtolower($identifiant)])
            ->first();

        // Vérifier que l'utilisateur existe et que le mot de passe est correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Identifiants incorrects.'], 401);
        }

        // Aucun compte suspendu ne peut se connecter, quel que soit le rôle
        if ($user->est_suspendu) {
            return response()->json([
                'message' => 'Votre compte est suspendu. Contactez l\'administrateur.',
            ], 403);
        }

        // Si c'est un enchérisseur, vérifier que son compte est validé
        if ($user->isEncherisseur() && !$user->est_valide) {
            return response()->json([
                'message' => 'Votre compte enchérisseur est en attente de validation.',
            ], 403);
        }

        // Créer le token Sanctum pour cette session
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
        // Charger le rôle et le wallet en même temps pour éviter plusieurs requêtes
        $user = $request->user()->load('role', 'encherisseur.wallet');

        return response()->json([
            'data' => [
                'id'          => $user->id,
                'nom_complet' => $user->nom_complet,
                'role'        => $user->role->nom,
                'telephone'   => $user->telephone,
                'email'       => $user->email,
                'est_valide'  => $user->est_valide,
                // optional() évite une erreur si l'utilisateur n'a pas de profil enchérisseur
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
        // Supprimer uniquement le token utilisé pour cette requête
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }
}
