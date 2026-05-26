<?php

namespace Database\Seeders;

use App\Models\Categorie;
use Illuminate\Database\Seeder;

class CategorieSeeder extends Seeder
{
    public function run(): void
    {
            \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Categorie::truncate();

        Categorie::insert([
            ['libelle' => 'Véhicules',      'description' => 'Voitures, motos, camions...'],
            ['libelle' => 'Immeubles',      'description' => 'Maisons, villas, terrains...'],
            ['libelle' => 'Animaux',        'description' => 'Bétail, animaux de ferme...'],
            ['libelle' => 'Équipements',    'description' => 'Machines, matériel professionnel...'],
            ['libelle' => 'Numéraires',     'description' => 'Espèces, devises...'],
            ['libelle' => 'Or',             'description' => 'Bijoux, lingots, pépites...'],
            ['libelle' => 'Autre',          'description' => 'Autre type de bien'],
        ]);
            \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
