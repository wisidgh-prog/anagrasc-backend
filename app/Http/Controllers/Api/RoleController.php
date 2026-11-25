<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * GET /api/roles
     * Liste tous les rôles avec leurs permissions.
     */
    public function index()
    {
        $roles = Role::with('permissions')->get();

        return response()->json(['data' => $roles]);
    }

    /**
     * GET /api/permissions
     * Liste toutes les permissions disponibles (pour la gestion des rôles).
     */
    public function permissions()
    {
        $permissions = Permission::orderBy('id')->get();

        return response()->json(['data' => $permissions]);
    }

    /**
     * POST /api/roles
     * Créer un nouveau rôle.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nom'         => 'required|string|unique:roles',
            'description' => 'nullable|string',
            'est_actif'   => 'boolean',
        ]);

        $role = Role::create([
            'nom'         => $request->nom,
            'description' => $request->description,
            'est_actif'   => $request->est_actif ?? true,
        ]);

        return response()->json(['data' => $role], 201);
    }

    /**
     * PUT /api/roles/{role}
     * Modifier un rôle existant.
     */
    public function update(Request $request, Role $role)
    {
        $request->validate([
            // 'ignore' l'ID actuel pour ne pas déclencher l'erreur "unique" si on garde le même nom
            'nom'         => 'sometimes|string|unique:roles,nom,' . $role->id,
            'description' => 'nullable|string',
            'est_actif'   => 'boolean',
        ]);

        $role->update([
            'nom'         => $request->nom ?? $role->nom,
            'description' => $request->description ?? $role->description,
            'est_actif'   => $request->est_actif ?? $role->est_actif,
        ]);

        return response()->json(['data' => $role]);
    }

    /**
     * DELETE /api/roles/{role}
     * Supprimer un rôle.
     */
    public function destroy(Role $role)
    {
        // Sécurité : ne jamais supprimer le rôle administrateur ni un rôle encore
        // attribué à des utilisateurs (sinon leurs accès sont cassés).
        if ($role->nom === 'administrateur') {
            return response()->json(['message' => 'Le rôle administrateur ne peut pas être supprimé.'], 422);
        }

        if ($role->users()->exists()) {
            return response()->json(['message' => 'Ce rôle est attribué à des utilisateurs ; réaffectez-les d\'abord.'], 422);
        }

        // La table pivot n'a pas de clé étrangère (moteur MyISAM) : on la nettoie.
        $role->permissions()->detach();
        $role->delete();

        return response()->json(['message' => 'Rôle supprimé.']);
    }

    /**
     * POST /api/roles/{role}/permissions
     * Synchroniser les permissions d'un rôle.
     * sync() remplace toutes les permissions existantes par celles envoyées.
     */
    public function syncPermissions(Request $request, Role $role)
    {
        // Le rôle administrateur est super-utilisateur : il doit conserver
        // toutes les permissions (sinon l'admin peut se verrouiller lui-même).
        if ($role->nom === 'administrateur') {
            return response()->json([
                'message' => 'Le rôle administrateur conserve automatiquement toutes les permissions.',
            ], 422);
        }

        // $request->input('permissions', []) retourne le tableau envoyé, ou un tableau vide par défaut
        $permissions = $request->input('permissions', []);

        $role->permissions()->sync($permissions);

        return response()->json(['message' => 'Permissions mises à jour.']);
    }
}
