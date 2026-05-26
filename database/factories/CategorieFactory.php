<?php

namespace Database\Factories;

use App\Models\Categorie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categorie>
 */
class CategorieFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    protected $model = Categorie::class;
    public function definition(): array
    {
        return [
            'libelle' => $this->faker->word(),
             'est_actif' => true,
        ];
    }
}
