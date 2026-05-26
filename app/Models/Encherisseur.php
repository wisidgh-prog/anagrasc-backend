<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Encherisseur extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Attributs assignables en masse
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'personne_id',   // Référence vers users (rôle = encherisseur)
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Utilisateur lié à ce profil enchérisseur.
     */
    public function personne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'personne_id');
    }

    /**
     * Wallet (portefeuille) de l'enchérisseur.
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Offres placées par cet enchérisseur.
     */
    public function offres(): HasMany
    {
        return $this->hasMany(Offre::class);
    }

    /**
     * Transactions financières de cet enchérisseur.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accesseurs
    |--------------------------------------------------------------------------
    */

    /**
     * Raccourci pour obtenir le solde du wallet.
     */
    public function getSoldeAttribute(): float
    {
        return $this->wallet ? $this->wallet->solde : 0.0;
    }
}
