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

    public function configure(): static
    {
        return $this->afterMaking(function (ClientEcacPowerOfAttorney $powerOfAttorney): void {
            if ($powerOfAttorney->client instanceof Client) {
                $powerOfAttorney->account_id = $powerOfAttorney->client->account_id;
            }
        })->afterCreating(function (ClientEcacPowerOfAttorney $powerOfAttorney): void {
            if ($powerOfAttorney->client instanceof Client && $powerOfAttorney->account_id !== $powerOfAttorney->client->account_id) {
                $powerOfAttorney->account_id = $powerOfAttorney->client->account_id;
                $powerOfAttorney->saveQuietly();
            }
        });
    }
}
