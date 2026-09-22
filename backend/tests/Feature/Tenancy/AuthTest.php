<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_registered_user_becomes_super_admin(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Chefona', 'email' => 'chef@opmoni.dev', 'password' => 'password123',
            'company' => 'HQ', 'size' => 'Só eu',
        ]);
        $response->assertCreated();
        $this->assertTrue(User::firstWhere('email', 'chef@opmoni.dev')->isSuperAdmin());
    }
}
