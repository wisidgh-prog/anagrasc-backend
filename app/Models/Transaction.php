<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Constantes de type
    |--------------------------------------------------------------------------
    */
    public const TYPE_DEPOT                 = 'depot';
    public const TYPE_PRELEVEMENT_CAUTION   = 'prelevement_caution';
    public const TYPE_REMBOURSEMENT_CAUTION = 'remboursement_caution';
    public const TYPE_PAIEMENT_FINAL        = 'paiement_final';
    public const TYPE_PENALITE              = 'penalite';

    /*
    |--------------------------------------------------------------------------
    | Constantes de statut
    |--------------------------------------------------------------------------
    */
    public const STATUT_OK          = 'ok';
    public const STATUT_ECHEC       = 'echec';
    public const STATUT_EN_ATTENTE  = 'en_attente';

    /*
    |--------------------------------------------------------------------------
    | Attributs 
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'encherisseur_id',
        'session_id',
        'type',
        'montant',
        'statut',
        'reference_externe',
        'description',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts des attributs
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'montant' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Enchérisseur concerné par la transaction.
     */
    public function encherisseur(): BelongsTo
    {
        return $this->belongsTo(Encherisseur::class);
    }

    /**
     * Session d'enchère associée (peut être nulle pour un dépôt simple).
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SessionEnchere::class);
    }
}
