<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SessionEnchere;
use App\Services\AdjudicationService;

class ClotureEncheresCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'encheres:cloturer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clôture les sessions dont la date de fin est atteinte.';

    /**
     * Execute the console command.
     */
    public function __construct(
        private AdjudicationService $adjudicationService
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $sessions = SessionEnchere::where('statut', SessionEnchere::STATUT_EN_COURS)
            ->where('date_fin', '<=', now())
            ->get();

        foreach ($sessions as $session) {
            $this->adjudicationService->cloturer($session);
        }

        $this->info(count($sessions) . ' session(s) clôturée(s).');
    }
}

