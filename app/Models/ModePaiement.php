<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModePaiement extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Table associée
    |--------------------------------------------------------------------------
    */
    protected $table = 'mode_paiements';

    /*
    |--------------------------------------------------------------------------
    | Attributs assignables en masse
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'nom',
        'description',
        'est_actif',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts des attributs
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'est_actif' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes (requêtes réutilisables)
    |--------------------------------------------------------------------------
    */

    /**
     * Filtre uniquement les modes actifs.
     */
    public function scopeActif($query)
    {
        return $query->where('est_actif', true);
    }
}
