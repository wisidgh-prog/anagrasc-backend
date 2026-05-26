<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Démarre automatiquement les sessions à l'heure prévue
Schedule::command('encheres:demarrer')->everyMinute();

// Clôture les sessions à leur date de fin exacte
Schedule::command('encheres:cloturer')->everyMinute();

// Traite les défaillances de paiement chaque jour à 8h
Schedule::command('encheres:relancer-paiements')->dailyAt('08:00');
