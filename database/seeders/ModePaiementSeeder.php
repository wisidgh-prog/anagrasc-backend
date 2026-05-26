<?php

namespace Database\Seeders;

use App\Models\ModePaiement;
use Illuminate\Database\Seeder;

class ModePaiementSeeder extends Seeder
{
    public function run(): void
    {
         \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        ModePaiement::truncate();

        ModePaiement::insert([
            ['nom' => 'orange_money', 'description' => 'Orange Money', 'est_actif' => true],
            ['nom' => 'moov_money',   'description' => 'Moov Money',   'est_actif' => true],
            ['nom' => 'coris_money',  'description' => 'Coris Money',  'est_actif' => true],
        ]);
         \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
