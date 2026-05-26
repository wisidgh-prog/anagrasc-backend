<?php

namespace App\Services;

use App\Models\AppLog;
use App\Models\User;

class LogService
{
    /**
     * Enregistre une action dans la table de logs.
     */
    public function enregistrer(?User $user, string $action, string $description, array $meta = []): AppLog
    {
        return AppLog::create([
            'user_id'     => $user?->id,
            'action'      => $action,
            'description' => $description,
            'meta'        => $meta,
            'horodatage'  => now(),
        ]);
    }
}
