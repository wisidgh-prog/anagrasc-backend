<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categorie;

class CategorieController extends Controller
{
    /**
     * GET /api/categories
     * Public – Liste des catégories de biens.
     */
    public function index()
    {
        // On retourne uniquement l'id et le libellé, triés par ordre alphabétique
        $categories = Categorie::select('id', 'libelle')
            ->orderBy('libelle')
            ->get();

        return response()->json(['data' => $categories]);
    }
}
