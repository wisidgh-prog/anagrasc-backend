<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionEnchere extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Constantes de statut
    |--------------------------------------------------------------------------
    */
    public const STATUT_A_VENIR     = 'a_venir';
    public const STATUT_EN_COURS    = 'en_cours';
    public const STATUT_TERMINEE    = 'terminee';
    public const STATUT_INTERROMPUE = 'interrompue';

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */
    protected $table    = 'sessions_enchere';

    protected $fillable = [
        'bien_id',
        'commissaire_id',
        'superviseur_id',
        'date_debut',
        'date_fin',
        'mise_a_prix_effective',
        'montant_min_surenchere',
        'prix_reserve',
        'statut',
        'offre_gagnante_id',
        'paiement_effectue_le',
    ];

    protected $casts = [
        'date_debut'             => 'datetime',
        'date_fin'               => 'datetime',
        'mise_a_prix_effective'  => 'decimal:2',
        'montant_min_surenchere' => 'decimal:2',
        'prix_reserve'           => 'decimal:2',
        'paiement_effectue_le'   => 'datetime',
    ];

    protected $hidden = [
        'prix_reserve', // ne jamais exposer publiquement
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    public function commissaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commissaire_id');
    }

    public function superviseur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superviseur_id');
    }

    public function offres(): HasMany
    {
        return $this->hasMany(Offre::class, 'session_id');
    }

    public function offreGagnante(): BelongsTo
    {
        return $this->belongsTo(Offre::class, 'offre_gagnante_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'session_id');
    }

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
     * Nombre de secondes restantes jusqu'à la fin.
     * Retourne 0 si pas encore commencée, terminée ou interrompue.
     */
    public function getSecondesRestantesAttribute(): int
    {
        $maintenant = now();

        // Pas encore commencée
        if ($maintenant->lessThan($this->date_debut)) {
            return 0;
        }

        // Terminée ou interrompue
        if (in_array($this->statut, [self::STATUT_TERMINEE, self::STATUT_INTERROMPUE])) {
            return 0;
        }

        return max(0, $maintenant->diffInSeconds($this->date_fin, false));
    }

    /*
    |--------------------------------------------------------------------------
    | Méthodes métier
    |--------------------------------------------------------------------------
    */

    /**
     * Vérifie si la session accepte des offres.
     *
     * Logique :
     * - Terminée ou interrompue → jamais active
     * - Dans la plage date_debut → date_fin → active
     *   (même si le scheduler n'a pas encore mis à jour le statut de a_venir à en_cours)
     */
    public function estActive(): bool
    {
        $maintenant = now();

        // Terminée ou interrompue → refus immédiat
        if (in_array($this->statut, [self::STATUT_TERMINEE, self::STATUT_INTERROMPUE])) {
            return false;
        }

        // Active si on est dans la fenêtre date_debut → date_fin
        return $maintenant->greaterThanOrEqualTo($this->date_debut)
            && $maintenant->lessThanOrEqualTo($this->date_fin);
    }

    /**
     * Calcule le montant de la caution requise pour participer.
     * Taux défini dans la configuration (0.1% par défaut, minimum 1000 FCFA).
     */
    public function getCautionRequise(): float
    {
        $taux = (float) Configuration::valeur('taux_caution', '0.1');
        $min  = (float) Configuration::valeur('caution_minimum', '1000');
        return max($this->mise_a_prix_effective * $taux / 100, $min);
    }
}
