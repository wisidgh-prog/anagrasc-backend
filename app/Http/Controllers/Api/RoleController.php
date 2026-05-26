<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    // GET /api/roles
    public function index()
    {
        return response()->json(['data' => Role::with('permissions')->get()]);
    }

    // POST /api/roles
    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string|unique:roles',
            'description' => 'nullable|string',
            'est_actif' => 'boolean',
        ]);
        $role = Role::create($data);
        return response()->json(['data' => $role], 201);
    }

    // PUT /api/roles/{role}
    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'nom' => 'sometimes|string|unique:roles,nom,'.$role->id,
            'description' => 'nullable|string',
            'est_actif' => 'boolean',
        ]);
        $role->update($data);
        return response()->json(['data' => $role]);
    }

    // DELETE /api/roles/{role}
    public function destroy(Role $role)
    {
        $role->delete();
        return response()->json(['message' => 'Rôle supprimé']);
    }

    // POST /api/roles/{role}/permissions
    public function syncPermissions(Request $request, Role $role)
    {
        $role->permissions()->sync($request->input('permissions', []));
        return response()->json(['message' => 'Permissions mises à jour']);
    }
}
