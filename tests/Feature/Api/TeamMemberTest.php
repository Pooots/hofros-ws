<?php

namespace Tests\Feature\Api;

use App\Models\TeamMember;

class TeamMemberTest extends ApiTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndexIncludesOwnerRowAndInvitedMembers(): void
    {
        $owner = $this->authenticate();
        TeamMember::factory()->create([
            'owner_user_uuid' => $owner->uuid,
            'email' => 'staff@example.com',
            'role' => TeamMember::ROLE_STAFF,
        ]);

        $response = $this->getJson('/api/v1/configuration/team')
            ->assertOk()
            ->assertJsonStructure([
                'team' => [
                    ['uuid', 'name', 'email', 'role', 'initials'],
                ],
            ]);

        $emails = collect($response->json('team'))->pluck('email')->all();
        $this->assertContains($owner->email, $emails);
        $this->assertContains('staff@example.com', $emails);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testInviteCreatesMember(): void
    {
        $owner = $this->authenticate();

        $this->postJson('/api/v1/configuration/team/invite', [
            'email' => 'new@example.com',
            'name' => 'New Member',
            'role' => TeamMember::ROLE_MANAGER,
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.role', TeamMember::ROLE_MANAGER);

        $this->assertDatabaseHas('team_members', [
            'owner_user_uuid' => $owner->uuid,
            'email' => 'new@example.com',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testInviteBlocksAccountOwnerEmail(): void
    {
        $owner = $this->authenticate();

        $this->postJson('/api/v1/configuration/team/invite', [
            'email' => $owner->email,
        ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testInviteBlocksDuplicateEmail(): void
    {
        $owner = $this->authenticate();
        TeamMember::factory()->create([
            'owner_user_uuid' => $owner->uuid,
            'email' => 'dup@example.com',
        ]);

        $this->postJson('/api/v1/configuration/team/invite', [
            'email' => 'dup@example.com',
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testInviteValidatesEmail(): void
    {
        $this->authenticate();

        $this->postJson('/api/v1/configuration/team/invite', [
            'email' => 'not-an-email',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateChangesRole(): void
    {
        $owner = $this->authenticate();
        $member = TeamMember::factory()->create([
            'owner_user_uuid' => $owner->uuid,
            'role' => TeamMember::ROLE_STAFF,
        ]);

        $this->patchJson('/api/v1/configuration/team/'.$member->uuid, [
            'role' => TeamMember::ROLE_MANAGER,
        ])
            ->assertOk()
            ->assertJsonPath('data.role', TeamMember::ROLE_MANAGER);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateCannotChangeOwnRole(): void
    {
        $owner = $this->authenticate();
        $ownerRow = TeamMember::factory()->create([
            'owner_user_uuid' => $owner->uuid,
            'email' => $owner->email,
            'role' => TeamMember::ROLE_ADMIN,
        ]);

        $this->patchJson('/api/v1/configuration/team/'.$ownerRow->uuid, [
            'role' => TeamMember::ROLE_STAFF,
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testInitialsFallBackToEmailWhenNameEmpty(): void
    {
        $owner = $this->authenticate();
        TeamMember::factory()->create([
            'owner_user_uuid' => $owner->uuid,
            'name' => '',
            'email' => 'zorg@example.com',
            'role' => TeamMember::ROLE_STAFF,
        ]);

        $response = $this->getJson('/api/v1/configuration/team')->assertOk();

        $row = collect($response->json('team'))->firstWhere('email', 'zorg@example.com');
        $this->assertNotNull($row);
        $this->assertSame('ZO', $row['initials']);
    }
}
