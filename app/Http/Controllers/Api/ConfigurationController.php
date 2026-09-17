<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppLog;
use App\Models\Configuration;
use Illuminate\Http\Request;

class ConfigurationController extends Controller
{
    /**
     * GET /api/configurations-publiques
     * auth:sanctum (tout rôle connecté)
     * Liste des paramètres non sensibles utiles à l'affichage/aux formulaires
     * (ex. la surenchère minimum, visible dans l'UI du commissaire).
     */
    public function publiques()
    {
        $cles = ['min_surenchere', 'taux_caution', 'caution_minimum'];

        $configs = Configuration::whereIn('cle', $cles)
            ->select('cle', 'valeur', 'description')
            ->orderBy('cle')
            ->get();

        return response()->json(['data' => $configs]);
    }

    /**
     * GET /api/configurations
     * auth:sanctum + role:administrateur
     * Liste de tous les paramètres de configuration du système.
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
     * Modifier la valeur d'un paramètre système (CDC Scénario 7).
     */
    public function update(Request $request, Configuration $configuration)
    {
        $request->validate([
            'valeur' => 'required|string|max:191',
        ]);

        // On garde l'ancienne valeur pour l'enregistrer dans les logs
        $ancienneValeur = $configuration->valeur;

        $configuration->update(['valeur' => $request->valeur]);

        // Enregistrer la modification dans les logs
        AppLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'modification_configuration',
            'description' => "Configuration '{$configuration->cle}' modifiée : {$ancienneValeur} → {$request->valeur}",
            'horodatage'  => now(),
        ]);

        return response()->json([
            'message' => 'Paramètre mis à jour.',
            'data'    => $configuration->fresh(),
        ]);
    }
}
