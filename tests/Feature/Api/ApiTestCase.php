<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Base test case for API feature tests.
 *
 * Refreshes the database between tests, exposes a Sanctum-authenticated
 * helper, and ensures all requests negotiate JSON.
 */
abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $authUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeaders([
            'Accept' => 'application/json',
        ]);
    }

    /**
     * Create a user and authenticate them via Sanctum.
     */
    protected function authenticate(?User $user = null): User
    {
        $user = $user ?? User::factory()->create();
        Sanctum::actingAs($user);
        $this->authUser = $user;

        return $user;
    }
}
