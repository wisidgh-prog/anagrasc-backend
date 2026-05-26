<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Attributs assignables en masse
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'cle',
        'valeur',
        'description',
    ];

    /*
    |--------------------------------------------------------------------------
    | Méthodes statiques (utilitaires pour tout le système)
    |--------------------------------------------------------------------------
    */

    /**
     * Récupère la valeur d'une clé de configuration.
     * Utilisé dans tout le code pour éviter des requêtes répétées.
     *
     * @param string $cle    ex: 'taux_caution'
     * @param mixed  $defaut valeur retournée si la clé n'existe pas
     */
    public static function valeur(string $cle, mixed $defaut = null): mixed
    {
        return static::where('cle', $cle)->value('valeur') ?? $defaut;
    }

    /**
     * Crée ou met à jour une clé de configuration.
     * Pratique pour les seeders ou la page admin de configuration.
     */
    public static function definir(string $cle, string $valeur, ?string $description = null): void
    {
        static::updateOrCreate(
            ['cle' => $cle],
            ['valeur' => $valeur, 'description' => $description]
        );
    }
}
