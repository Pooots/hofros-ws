<?php

namespace Tests\Feature\Api;

class HealthTest extends ApiTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testRootEndpointReturnsOk(): void
    {
        $this->getJson('/api')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testHealthEndpointResponds(): void
    {
        $this->getJson('/api/health')
            ->assertSuccessful();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testV1HealthEndpointResponds(): void
    {
        $this->getJson('/api/v1/health')
            ->assertSuccessful();
    }
}
