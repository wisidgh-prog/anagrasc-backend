<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppLog extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Table associée
    |--------------------------------------------------------------------------
    */
    protected $table = 'logs';

    /*
    |--------------------------------------------------------------------------
    | Timestamps
    |--------------------------------------------------------------------------
    | Les logs sont immuables : pas de updated_at.
    | On désactive donc la gestion automatique des timestamps.
    */
    public $timestamps = false;

    /*
    |--------------------------------------------------------------------------
    | Attributs assignables en masse
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'meta',
        'horodatage',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts des attributs
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'meta'       => 'array',
        'horodatage' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Utilisateur ayant effectué l'action (peut être null si action système).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
