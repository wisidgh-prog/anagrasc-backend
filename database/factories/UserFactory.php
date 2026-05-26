<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $role = Role::firstOrCreate(
            ['nom' => 'encherisseur'],
            ['description' => 'Enchérisseur', 'est_actif' => true]
        );

        return [
            'role_id'       => $role->id,
            'type_personne' => 'physique',
            'nom'           => $this->faker->lastName(),
            'prenom'        => $this->faker->firstName(),
            'date_naissance' => $this->faker->date('Y-m-d', '-18 years'),
            'lieu_naissance' => $this->faker->city(),
            'telephone'     => '+226' . $this->faker->numerify('########'),
            'email'         => $this->faker->unique()->safeEmail(),
            'ville'         => 'Ouagadougou',
            'password'      => Hash::make('password'),
            'type_piece'    => 'cnib',
            'num_piece'     => $this->faker->numerify('BF-#########'),
            'est_valide'    => true,
            'est_suspendu'  => false,
        ];
    }

    public function nonValide(): static
    {
        return $this->state(fn () => ['est_valide' => false]);
    }

    public function suspendu(): static
    {
        return $this->state(fn () => ['est_suspendu' => true, 'est_valide' => false]);
    }
}
