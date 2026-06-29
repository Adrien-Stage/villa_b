<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name'          => $this->faker->firstName(),
            'last_name'           => $this->faker->lastName(),
            'email'               => $this->faker->unique()->safeEmail(),
            'phone'               => $this->faker->phoneNumber(),
            'nationality'         => $this->faker->randomElement(['CM', 'FR', 'SN', 'CI', 'NG']),
            'id_document_type'    => $this->faker->randomElement(['passport', 'id_card']),
            'id_document_number'  => strtoupper($this->faker->bothify('??######')),
            'city'                => $this->faker->randomElement(['Bafoussam', 'Douala', 'Yaoundé', 'Paris']),
            'country'             => $this->faker->randomElement(['CM', 'FR', 'SN', 'CI']),
            'loyalty_points'      => $this->faker->numberBetween(0, 5000),
            'loyalty_level'       => $this->faker->randomElement(['bronze', 'silver', 'gold']),
            'total_nights_stayed' => $this->faker->numberBetween(0, 30),
            'total_spent'         => $this->faker->numberBetween(0, 50000000),
            'is_vip'              => $this->faker->boolean(10),
            'is_blacklisted'      => false,
        ];
    }
}