<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Photo extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Attributs assignables en masse
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'bien_id',
        'url',        // Chemin relatif dans le storage
        'ordre',      // Position pour le tri dans la galerie
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts des attributs
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'ordre' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Bien auquel la photo est rattachée.
     */
    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }
}
