<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use Illuminate\Http\Request;

class ConfigurationController extends Controller
{
    /**
     * GET /api/configurations
     * auth:sanctum + role:administrateur
     *
     * Liste des paramètres de configuration.
     */
    public function index()
    {
        $configs = Configuration::select('id', 'cle', 'valeur', 'description')
            ->orderBy('cle')
            ->get();

        return response()->json(['data' => $configs]);
    }

    /**
     * PUT /api/configurations/{configuration}
     * auth:sanctum + role:administrateur
     *
     * Modifie la valeur d'un paramètre système (CDC Scénario 7).
     */
    public function update(Request $request, Configuration $configuration)
    {
        $validated = $request->validate([
            'valeur' => 'required|string|max:191',
        ]);

        $ancienneValeur = $configuration->valeur;
        $configuration->update(['valeur' => $validated['valeur']]);

        // Journalisation de la modification (utilisation du LogService via injection
        // si disponible, sinon un simple log applicatif)
        \Log::info("Configuration modifiée : {$configuration->cle} changée de {$ancienneValeur} à {$validated['valeur']}");

        return response()->json([
            'message' => 'Paramètre mis à jour.',
            'data'    => $configuration->fresh(),
        ]);
    }
}
