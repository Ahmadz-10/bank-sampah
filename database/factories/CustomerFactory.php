<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => null,
            'name' => fake('id_ID')->name(),
            'id_number' => fake()->unique()->numerify('################'),
            'phone' => fake()->numerify('08##########'),
            'address' => fake('id_ID')->streetAddress(),
            'joined_at' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'is_active' => true,
        ];
    }
}
