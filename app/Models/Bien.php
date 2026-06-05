<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bien extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Constantes de statut
    |--------------------------------------------------------------------------
    | Correction appliquée : suppression du statut 'en_attente_validation'.
    | Le commissaire publie directement avec le statut 'publie'.
    */
    public const STATUT_EN_ATTENTE_VALIDATION = 'en_attente_validation'; // En attente de validation
    public const STATUT_PUBLIE   = 'publie';      // Visible en catalogue
    public const STATUT_EN_COURS = 'en_cours';    // Enchère active
    public const STATUT_VENDU    = 'vendu';       // Adjugé et payé
    public const STATUT_ANNULE   = 'annule';      // Retiré de la vente
    public const STATUT_REJETE   = 'rejete';      // Refusé lors de validation
    /*
    |--------------------------------------------------------------------------
    | Attributs assignables en masse
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'categorie_id',
        'cree_par',
        'titre',
        'description',
        'mise_a_prix',
        'lieu_retrait',
        'horaires_visite',
        'remarques',
        'statut',
        'motif_rejet',
        'date_rejet',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts des attributs
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'mise_a_prix' => 'decimal:2',
        'date_rejet'  => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Catégorie du bien.
     */
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    /**
     * Commissaire-priseur ayant créé le bien.
     */
    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    /**
     * Photos associées au bien (triées par ordre).
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('ordre');
    }

    /**
     * Sessions d'enchères programmées sur ce bien.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(SessionEnchere::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Méthodes métier
    |--------------------------------------------------------------------------
    */

    /**
     * Indique si le bien peut être modifié (pas en cours ni vendu).
     */
    public function estModifiable(): bool
    {
        return !in_array($this->statut, [
            self::STATUT_EN_ATTENTE_VALIDATION,
            self::STATUT_REJETE,
             
             ]);
    }

    /**
     * Indique si le bien peut être supprimé (seulement s'il est publié
     * et n'a jamais fait l'objet d'une session).
     */
    public function estSupprimable(): bool
    {
        return $this->statut === self::STATUT_PUBLIE
            && $this->sessions()->doesntExist();
    }
}
