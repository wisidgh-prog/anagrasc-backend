<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
         \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Permission::truncate();
        // Vider la table pivot
        \DB::table('role_permission')->truncate();

        // Définir les permissions
        $permissions = [
            ['nom' => 'gerer_biens',          'description' => 'Créer, modifier, supprimer des biens'],
            ['nom' => 'gerer_sessions',       'description' => 'Créer, modifier des sessions d\'enchères'],
            ['nom' => 'gerer_utilisateurs',   'description' => 'Valider, rejeter, suspendre des comptes'],
            ['nom' => 'consulter_logs',       'description' => 'Voir les logs de traçabilité'],
            ['nom' => 'modifier_config',      'description' => 'Modifier les paramètres système'],
            ['nom' => 'superviser_encheres',  'description' => 'Surveiller les enchères en direct'],
            ['nom' => 'signaler_incident',    'description' => 'Signaler un incident'],
            ['nom' => 'traiter_incident',     'description' => 'Prendre une décision sur un incident'],
            ['nom' => 'participer_encheres',  'description' => 'Participer aux enchères'],
            ['nom' => 'gerer_wallet',         'description' => 'Recharger son wallet, consulter ses transactions'],
        ];

        foreach ($permissions as $perm) {
            Permission::create(array_merge($perm, ['est_actif' => true]));
        }

        // Attribuer les permissions aux rôles
        $admin = Role::where('nom', 'administrateur')->first();
        $commissaire = Role::where('nom', 'commissaire_priseur')->first();
        $superviseur = Role::where('nom', 'superviseur')->first();
        $encherisseur = Role::where('nom', 'encherisseur')->first();

        // Admin : toutes les permissions
        if ($admin) {
            $admin->permissions()->sync(Permission::pluck('id'));
        }

        // Commissaire
        if ($commissaire) {
            $commissaire->permissions()->sync(
                Permission::whereIn('nom', [
                    'gerer_biens', 'gerer_sessions', 'traiter_incident',
                    'superviser_encheres'
                ])->pluck('id')
            );
        }

        // Superviseur
        if ($superviseur) {
            $superviseur->permissions()->sync(
                Permission::whereIn('nom', ['superviser_encheres', 'signaler_incident'])->pluck('id')
            );
        }

        // Enchérisseur
        if ($encherisseur) {
            $encherisseur->permissions()->sync(
                Permission::whereIn('nom', ['participer_encheres', 'gerer_wallet'])->pluck('id')
            );
        }
            \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
