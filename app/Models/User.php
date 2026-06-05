<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Attributs assignables en masse.
     */
    protected $fillable = [
        'role_id',
        'type_personne',
        'nom',
        'prenom',
        'date_naissance',
        'lieu_naissance',
        'raison_sociale',
        'num_rccm',
        'num_ifu',
        'representant_legal_nom',
        'representant_legal_prenom',
        'representant_legal_date_naissance',
        'representant_legal_lieu_naissance',
        'representant_legal_fonction',
        'telephone',
        'email',
        'ville',
        'password',
        'type_piece',
        'num_piece',
        'piece_recto_url',
        'piece_verso_url',
        'est_valide',
        'est_suspendu',
        'valide_le',
        'valide_par',
        'piece_recto_url',   // Accès sécurisé uniquement via route dédiée
        'piece_verso_url',
    ];

    /**
     * Attributs cachés dans les réponses JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',

    ];

    /**
     * Casts des attributs.
     */
    protected $casts = [
        'date_naissance'                      => 'date',
        'representant_legal_date_naissance'   => 'date',
        'est_valide'                          => 'boolean',
        'est_suspendu'                        => 'boolean',
        'valide_le'                           => 'datetime',
        'password'                            => 'hashed',
    ];

     protected $appends = ['nom_complet'];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Rôle de l'utilisateur.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Profil enchérisseur associé (si l'utilisateur a le rôle enchérisseur).
     */
    public function encherisseur()
    {
        return $this->hasOne(Encherisseur::class, 'personne_id');
    }

    /**
     * Biens créés par cet utilisateur (commissaire-priseur).
     */
    public function biensCrees()
    {
        return $this->hasMany(Bien::class, 'cree_par');
    }

    /**
     * Sessions d'enchères où l'utilisateur est commissaire.
     */
    public function sessionsCommissaire()
    {
        return $this->hasMany(SessionEnchere::class, 'commissaire_id');
    }

    /**
     * Sessions d'enchères où l'utilisateur est superviseur.
     */
    public function sessionsSuperviseur()
    {
        return $this->hasMany(SessionEnchere::class, 'superviseur_id');
    }

    /**
     * Incidents signalés par ce superviseur.
     */
    public function incidentsSignales()
    {
        return $this->hasMany(Incident::class, 'superviseur_id');
    }

    /**
     * Incidents traités par cet utilisateur.
     */
    public function incidentsTraites()
    {
        return $this->hasMany(Incident::class, 'traite_par');
    }

    /*
    |--------------------------------------------------------------------------
    | Méthodes de vérification de rôle
    |--------------------------------------------------------------------------
    */

    /**
     * Vérifie si l'utilisateur a un rôle donné.
     */
    public function hasRole(string $roleNom): bool
    {
        return $this->role && $this->role->nom === $roleNom;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('administrateur');
    }

    public function isCommissaire(): bool
    {
        return $this->hasRole('commissaire_priseur');
    }

    public function isSuperviseur(): bool
    {
        return $this->hasRole('superviseur');
    }

    public function isEncherisseur(): bool
    {
        return $this->hasRole('encherisseur');
    }

    /*
    |--------------------------------------------------------------------------
    | Accesseurs
    |--------------------------------------------------------------------------
    */

    /**
     * Retourne le nom complet selon le type de personne.
     */
    public function getNomCompletAttribute(): string
    {
        if ($this->type_personne === 'morale') {
            return $this->raison_sociale ?? 'Personne morale';
        }
        return trim("{$this->prenom} {$this->nom}");
    }

    /*
    |--------------------------------------------------------------------------
    | Méthodes métier
    |--------------------------------------------------------------------------
    */

    /**
     * Indique si l'utilisateur peut participer à une enchère.
     */
    public function peutEncherir(): bool
    {
        return $this->isEncherisseur()
            && $this->est_valide
            && !$this->est_suspendu
            && $this->encherisseur !== null
            && $this->encherisseur->wallet !== null;
    }
}
