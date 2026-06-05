<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        // $request->input('permissions', []) retourne le tableau envoyé, ou un tableau vide par défaut
        $permissions = $request->input('permissions', []);

        $role->permissions()->sync($permissions);

        return response()->json(['message' => 'Permissions mises à jour.']);
    }
}
