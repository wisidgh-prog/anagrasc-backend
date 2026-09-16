<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Constantes de statut
    |--------------------------------------------------------------------------
    */
    public const STATUT_OUVERT = 'ouvert';
    public const STATUT_TRAITE = 'traite';
    public const STATUT_CLOS   = 'clos';

    /*
    |--------------------------------------------------------------------------
    | Constantes de décision (CDC Scénario 6)
    |--------------------------------------------------------------------------
    */
    public const DECISION_MAINTIEN        = 'maintien';
    public const DECISION_ANNULATION      = 'annulation';
    public const DECISION_REPROGRAMMATION = 'reprogrammation';

    /*
    |--------------------------------------------------------------------------
    | Attributs assignables en masse
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'session_id',
        'superviseur_id',
        'traite_par',
        'type_incident',
        'description',
        'id_concerne',
        'statut',
        'decision',
        'note_decision',
        'date_signalement',
        'date_traitement',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts des attributs
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'date_signalement' => 'datetime',
        'date_traitement'  => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Session d'enchère concernée par l'incident.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SessionEnchere::class,'session_id');
    }

    /**
     * Superviseur ayant signalé l'incident.
     */
    public function superviseur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superviseur_id');
    }

    /**
     * Utilisateur ayant traité l'incident (commissaire ou admin).
     */
    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }
}
