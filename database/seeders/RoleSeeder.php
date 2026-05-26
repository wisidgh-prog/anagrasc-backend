<?php

namespace Database\Seeders;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
     public function run(): void
    {
         \DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Vider les tables avant de les repeupler (idempotent)
        Role::truncate();

        // Création des 4 rôles
        $admin = Role::create([
            'nom'         => 'administrateur',
            'description' => 'Administrateur système – comptes et configuration',
            'est_actif'   => true,
        ]);

        $commissaire = Role::create([
            'nom'         => 'commissaire_priseur',
            'description' => 'Commissaire-priseur judiciaire ANAGRASC',
            'est_actif'   => true,
        ]);

        $superviseur = Role::create([
            'nom'         => 'superviseur',
            'description' => 'Agent supervisant les sessions',
            'est_actif'   => true,
        ]);

        $encherisseur = Role::create([
            'nom'         => 'encherisseur',
            'description' => 'Participant aux enchères (physique/morale)',
            'est_actif'   => true,
        ]);
            \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
