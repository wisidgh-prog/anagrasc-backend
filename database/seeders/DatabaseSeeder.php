<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
          \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        // Appeler nos seeders dans l'ordre des dépendances
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            ConfigurationSeeder::class,
            CategorieSeeder::class,
            ModePaiementSeeder::class,
            UserSeeder::class,        // Contient l'admin et les utilisateurs de test
        ]);
          \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
