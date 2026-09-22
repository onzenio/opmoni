<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Client;
use App\Models\ClientEcacPowerOfAttorney;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientEcacPowerOfAttorney>
 */
class ClientEcacPowerOfAttorneyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'client_id' => Client::factory(),
            'starts_at' => today()->subMonth(),
            'expires_at' => today()->addYear(),
            'notes' => null,
        ];
    }
}
