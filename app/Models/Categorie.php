<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categorie extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Attributs assignables en masse
    |--------------------------------------------------------------------------
    */
    use HasFactory;
    protected $fillable = [
        'libelle',      // Nom de la catégorie (ex: "Véhicules")
        'description',  // Description facultative
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Une catégorie contient plusieurs biens.
     */
    public function biens(): HasMany
    {
        return $this->hasMany(Bien::class);
    }
}
