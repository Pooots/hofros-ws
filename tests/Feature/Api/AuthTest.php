<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthTest extends ApiTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testUserCanRegister(): void
    {
        $payload = [
            'merchant_name' => 'Acme Hospitality',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'contact_number' => '+639171234567',
            'address' => '123 Main St',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $payload)
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user' => ['uuid', 'email', 'merchant_name'],
            ]);

        $this->assertSame('Bearer', $response->json('token_type'));
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testRegisterValidatesRequiredFields(): void
    {
        $this->postJson('/api/v1/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'merchant_name', 'first_name', 'last_name',
                'contact_number', 'address', 'email', 'password',
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testRegisterRejectsDuplicateEmail(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/register', [
            'merchant_name' => 'X',
            'first_name' => 'X',
            'last_name' => 'X',
            'contact_number' => '111',
            'address' => 'A',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUserCanLoginWithValidCredentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'login@example.com',
            'password' => 'secret123',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user' => ['uuid', 'email'],
            ])
            ->assertJsonPath('user.uuid', $user->uuid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testLoginFailsWithWrongPassword(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testLoginFailsWithUnknownEmail(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ])
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testProtectedEndpointRequiresAuthentication(): void
    {
        $this->getJson('/api/v1/configuration/properties')
            ->assertStatus(401);
    }
}
