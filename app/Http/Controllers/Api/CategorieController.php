<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categorie;

class CategorieController extends Controller
{
    /**
     * GET /api/categories
     * Public – Liste des catégories.
     */
    public function index()
    {
        return response()->json([
            'data' => Categorie::select('id', 'libelle')->orderBy('libelle')->get(),
        ]);
    }
}
