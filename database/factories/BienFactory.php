<?php

namespace Database\Factories;

use App\Models\Bien;
use App\Models\Categorie;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @extends Factory<Bien>
 */
class BienFactory extends Factory
{
    use HasFactory;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roleCommissaire = Role::firstOrCreate(
            ['nom'=> 'commissaire_priseur'],
             ['description'=> 'commissaire','est_actif'=> true],
        );

        $commissaire = User::whereHas('role', fn($q) => $q->where('nom', 'commissaire_priseur'))->first()
            ?? User::factory()->create(['role_id' => $roleCommissaire->id, 'type_personne' => null]);

        return [
            'categorie_id'   => Categorie::factory(),
            'cree_par'       => $commissaire->id,
            'titre'          => $this->faker->sentence(4),
            'description'    => $this->faker->paragraph(2),
            'mise_a_prix'    => $this->faker->randomFloat(2, 100000, 10000000),
            'lieu_retrait'   => 'Abattoir de Kossodo, Ouagadougou',
            'statut'         => 'publie',
        ];
    }
}
