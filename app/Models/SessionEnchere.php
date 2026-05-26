<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SessionEnchere extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Constantes de statut
    |--------------------------------------------------------------------------
    */
    public const STATUT_A_VENIR    = 'a_venir';
    public const STATUT_EN_COURS   = 'en_cours';
    public const STATUT_TERMINEE   = 'terminee';
    public const STATUT_INTERROMPUE = 'interrompue';

    /*
    |--------------------------------------------------------------------------
    | Attributs assignables en masse
    |--------------------------------------------------------------------------
    */

    protected $table = 'sessions_enchere';
    protected $fillable = [
        'bien_id',
        'commissaire_id',
        'superviseur_id',
        'date_debut',
        'date_fin',
        'mise_a_prix_effective',
        'montant_min_surenchere',
        'prix_reserve',            // confidentiel
        'statut',
        'offre_gagnante_id',
        'paiement_effectue_le',   // date de confirmation du paiement
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts des attributs
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'date_debut'             => 'datetime',
        'date_fin'               => 'datetime',
        'mise_a_prix_effective'  => 'decimal:2',
        'montant_min_surenchere' => 'decimal:2',
        'prix_reserve'           => 'decimal:2',
        'paiement_effectue_le'   => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Attributs cachés dans les réponses JSON (sécurité)
    |--------------------------------------------------------------------------
    */
    protected $hidden = [
        'prix_reserve', // ne jamais exposer publiquement
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Bien mis en vente.
     */
    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    /**
     * Commissaire-priseur responsable.
     */
    public function commissaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commissaire_id');
    }

    /**
     * Superviseur affecté (optionnel).
     */
    public function superviseur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superviseur_id');
    }

    /**
     * Offres déposées pendant cette session.
     */
    public function offres(): HasMany
    {
        return $this->hasMany(Offre::class, 'session_id');
    }

    /**
     * Offre gagnante (définie à la clôture).
     */
    public function offreGagnante(): BelongsTo
    {
        return $this->belongsTo(Offre::class, 'offre_gagnante_id');
    }

    /**
     * Incidents signalés durant cette session.
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'session_id');
    }

    /**
     * Transactions liées à cette session.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'session_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Accesseurs
    |--------------------------------------------------------------------------
    */

    /**
     * Meilleure offre courante (la plus haute).
     */
    public function getMeilleureOffreAttribute(): ?Offre
    {
        return $this->offres()->orderByDesc('montant')->first();
    }

    /**
     * Nombre de secondes restantes jusqu'à la fin (0 si terminée).
     */
    public function getSecondesRestantesAttribute(): int
    {
        if (!$this->estActive()) {
            return 0;
        }
        return max(0, now()->diffInSeconds($this->date_fin, false));
    }

    /*
    |--------------------------------------------------------------------------
    | Méthodes métier
    |--------------------------------------------------------------------------
    */

    /**
     * Vérifie si la session est active (statut en_cours et dans la plage horaire).
     */
    public function estActive(): bool
    {
        return $this->statut === self::STATUT_EN_COURS
            && now()->between($this->date_debut, $this->date_fin);
    }

    /**
     * Calcule le montant de la caution requise pour participer.
     * Taux défini dans la configuration (0,1% par défaut, minimum 1000 FCFA).
     */
    public function getCautionRequise(): float
    {
        $taux = (float) Configuration::valeur('taux_caution', '0.1');
        $min  = (float) Configuration::valeur('caution_minimum', '1000');
        return max($this->mise_a_prix_effective * $taux / 100, $min);
    }
}
