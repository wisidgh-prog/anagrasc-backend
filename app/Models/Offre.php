<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offre extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Attributs 
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'session_id',
        'encherisseur_id',
        'montant',
        'id_anonyme',       // Format ENCH-001 (séquentiel par session)
        'est_gagnante',      // true si c'est l'offre retenue à la clôture
        'rang',              // 1 = gagnant, 2 = second, etc.
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts des attributs
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'montant'      => 'decimal:2',
        'est_gagnante' => 'boolean',
        'rang'         => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Session d'enchère à laquelle appartient cette offre.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SessionEnchere::class);
    }

    /**
     * Enchérisseur qui a placé l'offre (identité réelle, masquée en public).
     */
    public function encherisseur(): BelongsTo
    {
        return $this->belongsTo(Encherisseur::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Méthodes statiques (utilitaires)
    |--------------------------------------------------------------------------
    */

    /**
     * Génère le prochain ID anonyme pour une session donnée.
     * Format ENCH-001, ENCH-002, etc.
     */
    public static function genererIdAnonyme(int $sessionId): string
    {
        $count = self::where('session_id', $sessionId)->count() + 1;
        return 'ENCH-' . str_pad((string) $count, 3, '0', STR_PAD_LEFT);
    }
}
