<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SessionEnchere;
use App\Models\Bien;


class DemarreEncheresCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'encheres:demarrer';


    /**
     * The console command description.
     *
     * @var string
     */
      protected $description = 'Démarre les sessions dont la date de début est atteinte.';

    /**
     * Execute the console command.
     */
   public function handle(): void
    {
        $sessions = SessionEnchere::where('statut', SessionEnchere::STATUT_A_VENIR)
            ->where('date_debut', '<=', now())
            ->get();

        foreach ($sessions as $session) {
            $session->update(['statut' => SessionEnchere::STATUT_EN_COURS]);
            $session->bien->update(['statut' => Bien::STATUT_EN_COURS]);
        }

        $this->info(count($sessions) . ' session(s) démarrée(s).');
    }
}

