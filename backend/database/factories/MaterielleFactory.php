<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Materielle>
 */
class MaterielleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(), // <-- champ obligatoire
            'reference' => $this->faker->unique()->bothify('SN-####-??'),
            'type' => $this->faker->word(),
            'quantite' => $this->faker->numberBetween(1, 100),
            'sortie' => $this->faker->boolean(),
        ];
    }
}
