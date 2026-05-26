<?php

namespace Database\Seeders;

use App\Models\Configuration;
use Illuminate\Database\Seeder;

class ConfigurationSeeder extends Seeder
{
    public function run(): void
    {
         \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Configuration::truncate();

        Configuration::insert([
            [
                'cle'         => 'taux_caution',
                'valeur'      => '0.1',
                'description' => '0,1% de la mise à prix (CDC)',
            ],
            [
                'cle'         => 'caution_minimum',
                'valeur'      => '1000',
                'description' => 'Caution minimum en FCFA (CDC)',
            ],
            [
                'cle'         => 'taux_frais_adjudication',
                'valeur'      => '8',
                'description' => 'Frais post-adjudication en % (CDC)',
            ],
            [
                'cle'         => 'min_surenchere',
                'valeur'      => '1000',
                'description' => 'Surenchère minimum en FCFA',
            ],
            [
                'cle'         => 'delai_paiement_gagnant',
                'valeur'      => '48',
                'description' => 'Délai de paiement du gagnant en heures (CDC Scénario 9)',
            ],
            [
                'cle'         => 'delai_paiement_suivant',
                'valeur'      => '24',
                'description' => 'Délai de paiement pour le second enchérisseur en heures',
            ],
        ]);
            \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
