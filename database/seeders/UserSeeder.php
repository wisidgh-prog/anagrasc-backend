<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Encherisseur;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Récupération des rôles
        $adminRole        = Role::where('nom', 'administrateur')->first();
        $commissaireRole  = Role::where('nom', 'commissaire_priseur')->first();
        $superviseurRole  = Role::where('nom', 'superviseur')->first();
        $encherisseurRole = Role::where('nom', 'encherisseur')->first();

        // Admin par défaut
        $admin = User::create([
            'role_id'       => $adminRole->id,
            'type_personne' => null,
            'telephone'     => '70000000',
            'password'      => bcrypt('admin123'),
            'est_valide'    => true,
        ]);

        // Commissaire-priseur
        $commissaire = User::create([
            'role_id'       => $commissaireRole->id,
            'type_personne' => null,
            'nom'           => 'Ouédraogo',
            'prenom'        => 'Issouf',
            'telephone'     => '71000001',
            'password'      => bcrypt('commissaire123'),
            'est_valide'    => true,
        ]);

        // Superviseur
        $superviseur = User::create([
            'role_id'       => $superviseurRole->id,
            'type_personne' => null,
            'nom'           => 'Kaboré',
            'prenom'        => 'Mariam',
            'telephone'     => '72000002',
            'password'      => bcrypt('superviseur123'),
            'est_valide'    => true,
        ]);

        // Enchérisseur physique valide
        $encherisseur1 = User::create([
            'role_id'       => $encherisseurRole->id,
            'type_personne' => 'physique',
            'nom'           => 'Traoré',
            'prenom'        => 'Amidou',
            'date_naissance'=> '1988-03-15',
            'lieu_naissance'=> 'Bobo-Dioulasso',
            'telephone'     => '73000003',
            'email'         => 'amidou@example.bf',
            'ville'         => 'Ouagadougou',
            'password'      => bcrypt('password'),
            'type_piece'    => 'cnib',
            'num_piece'     => 'BF-123456789',
            'est_valide'    => true,
        ]);

        // Profil enchérisseur + wallet
        $encherisseurProfil = Encherisseur::create(['personne_id' => $encherisseur1->id]);
        Wallet::create(['encherisseur_id' => $encherisseurProfil->id, 'solde' => 50000]);

        // Enchérisseur non validé (pour tester les restrictions)
        User::create([
            'role_id'       => $encherisseurRole->id,
            'type_personne' => 'physique',
            'nom'           => 'Diallo',
            'prenom'        => 'Fatou',
            'date_naissance'=> '1992-08-20',
            'lieu_naissance'=> 'Ouagadougou',
            'telephone'     => '74000004',
            'password'      => bcrypt('password'),
            'type_piece'    => 'passeport',
            'num_piece'     => 'A1234567',
            'est_valide'    => false, // en attente de validation
        ]);
    }
}
