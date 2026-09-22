<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Client;
use App\Models\ClientCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientCertificate>
 */
class ClientCertificateFactory extends Factory
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
            'subject' => fake()->name().' :'.fake()->numerify('##############'),
            'serial_number' => fake()->unique()->bothify('??##########'),
            'valid_from' => now()->subMonths(6),
            'valid_until' => now()->addMonths(6),
            'original_filename' => fake()->slug().'.pfx',
            'storage_path' => null,
            'sha256' => hash('sha256', fake()->unique()->uuid()),
            'replaced_at' => null,
            'removed_at' => null,
        ];
    }
}
