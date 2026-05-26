<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'nom',
        'description',
        'est_actif'
        ];

    protected $casts = [
        'est_actif' => 'boolean',
    ];

    /**
     * Rôles possédant cette permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
