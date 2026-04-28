<?php

namespace Tests\Feature\Api;

use App\Http\Repositories\BookingPortalRepository;
use App\Models\BookingPortalConnection;
use App\Models\User;

class BookingPortalTest extends ApiTestCase
{
    private function seedPortalRows(): void
    {
        app(BookingPortalRepository::class)->ensureRowsForUser($this->authUser->uuid);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndexListsAllPortalsForUser(): void
    {
        $this->authenticate(User::factory()->create(['merchant_name' => 'Acme Stays']));

        $this->getJson('/api/v1/booking-portals')
            ->assertOk()
            ->assertJsonStructure([
                'channels' => [['id', 'uuid', 'name', 'isConnected', 'status']],
                'summary' => ['connectedChannels', 'totalListings', 'syncIssues'],
            ])
            ->assertJsonPath('summary.connectedChannels', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testDirectWebsiteSettingsReturnsDefaults(): void
    {
        $user = $this->authenticate(User::factory()->create(['merchant_name' => 'Acme Stays']));

        $this->getJson('/api/v1/booking-portals/direct-website/settings')
            ->assertOk()
            ->assertJsonPath('merchantSlug', 'acme-stays')
            ->assertJsonPath('merchantName', $user->merchant_name)
            ->assertJsonPath('guestPortalLive', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSaveDirectWebsiteContentPersists(): void
    {
        $this->authenticate();

        $this->patchJson('/api/v1/booking-portals/direct-website/content', [
            'headline' => 'Welcome to our place',
            'message' => 'Stay in comfort',
            'pageTitle' => 'Acme Stays',
        ])->assertOk()
            ->assertJsonStructure(['message', 'channel']);

        $this->assertDatabaseHas('booking_portal_connections', [
            'user_uuid' => $this->authUser->uuid,
            'portal_key' => 'direct_website',
            'guest_portal_headline' => 'Welcome to our place',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSaveDirectWebsiteDesignMarksCompleted(): void
    {
        $this->authenticate();

        $this->postJson('/api/v1/booking-portals/direct-website/design', [
            'headline' => 'Hello',
            'message' => 'Welcome',
        ])->assertOk();

        $this->assertDatabaseHas('booking_portal_connections', [
            'user_uuid' => $this->authUser->uuid,
            'portal_key' => 'direct_website',
            'guest_portal_design_completed' => 1,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSetDirectWebsiteLiveRequiresDesign(): void
    {
        $this->authenticate();

        $this->patchJson('/api/v1/booking-portals/direct-website/live', [
            'live' => true,
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSetDirectWebsiteLiveAfterDesign(): void
    {
        $this->authenticate();

        $this->postJson('/api/v1/booking-portals/direct-website/design', [
            'headline' => 'Hi',
            'message' => 'Hi',
        ])->assertOk();

        $this->patchJson('/api/v1/booking-portals/direct-website/live', [
            'live' => true,
        ])->assertOk();

        $this->assertDatabaseHas('booking_portal_connections', [
            'user_uuid' => $this->authUser->uuid,
            'portal_key' => 'direct_website',
            'guest_portal_live' => 1,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testConnectPortalSucceeds(): void
    {
        $this->authenticate();
        $this->seedPortalRows();

        $this->postJson('/api/v1/booking-portals/airbnb/connect')
            ->assertOk()
            ->assertJsonPath('channel.isConnected', true);

        $this->assertDatabaseHas('booking_portal_connections', [
            'user_uuid' => $this->authUser->uuid,
            'portal_key' => 'airbnb',
            'is_connected' => 1,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIndexFlagsSyncIssueOnConnectedPortal(): void
    {
        $this->authenticate();
        \App\Models\BookingPortalConnection::create([
            'user_uuid' => $this->authUser->uuid,
            'portal_key' => 'airbnb',
            'is_connected' => true,
            'has_sync_issue' => true,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/booking-portals')->assertOk();

        $airbnb = collect($response->json('channels'))->firstWhere('uuid', 'airbnb');
        $this->assertSame('Sync issue', $airbnb['status']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testConnectDirectWebsiteThrows(): void
    {
        $this->authenticate();
        $this->postJson('/api/v1/booking-portals/direct_website/connect')
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUnknownPortalReturns404(): void
    {
        $this->authenticate();
        $this->postJson('/api/v1/booking-portals/unknownx/connect')
            ->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateActiveRequiresConnection(): void
    {
        $this->authenticate();
        $this->seedPortalRows();
        $this->patchJson('/api/v1/booking-portals/airbnb/active', [
            'isActive' => true,
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUpdateActiveAfterConnect(): void
    {
        $this->authenticate();
        $this->seedPortalRows();
        $this->postJson('/api/v1/booking-portals/airbnb/connect')->assertOk();

        $this->patchJson('/api/v1/booking-portals/airbnb/active', [
            'isActive' => false,
        ])->assertOk()
            ->assertJsonPath('isActive', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSyncRequiresConnection(): void
    {
        $this->authenticate();
        $this->seedPortalRows();
        $this->postJson('/api/v1/booking-portals/airbnb/sync')
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSyncAfterConnect(): void
    {
        $this->authenticate();
        $this->seedPortalRows();
        $this->postJson('/api/v1/booking-portals/airbnb/connect')->assertOk();

        $this->postJson('/api/v1/booking-portals/airbnb/sync')
            ->assertOk()
            ->assertJsonStructure(['message', 'channel']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testAvailableListsDisconnectedPortals(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/v1/booking-portals/available')
            ->assertOk();

        $keys = collect($response->json('channels'))->pluck('uuid')->all();
        $this->assertNotContains('direct_website', $keys);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUploadDirectWebsiteHeroPersistsUrl(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->authenticate();

        $file = \Illuminate\Http\UploadedFile::fake()->image('hero.jpg', 1600, 900);

        $response = $this->postJson('/api/v1/booking-portals/direct-website/hero-image', [
            'image' => $file,
        ])
            ->assertOk()
            ->assertJsonStructure(['message', 'heroImageUrl']);

        $this->assertStringContainsString('/storage/guest-portal-heroes/', (string) $response->json('heroImageUrl'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testUploadDirectWebsiteHeroValidatesImage(): void
    {
        $this->authenticate();
        $this->postJson('/api/v1/booking-portals/direct-website/hero-image', [])
            ->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSetDirectWebsiteLiveUnpublishes(): void
    {
        $this->authenticate();
        $this->postJson('/api/v1/booking-portals/direct-website/design', [
            'headline' => 'Hi', 'message' => 'Hi',
        ])->assertOk();
        $this->patchJson('/api/v1/booking-portals/direct-website/live', ['live' => true])->assertOk();
        $this->patchJson('/api/v1/booking-portals/direct-website/live', ['live' => false])
            ->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSaveDirectWebsiteDesignSupportsVisualAndLayout(): void
    {
        $this->authenticate();

        $this->postJson('/api/v1/booking-portals/direct-website/design', [
            'headline' => 'Hi',
            'message' => 'Hi',
            'pageTitle' => 'Test Site',
            'themePreset' => 'bold_modern',
            'primaryColor' => '#1B4F8A',
            'accentColor' => '#F5A623',
            'heroImageUrl' => 'https://example.test/hero.jpg',
            'layout' => [
                'sectionsOrder' => ['hero', 'amenities', 'reviews'],
            ],
        ])
            ->assertOk();

        $this->assertDatabaseHas('booking_portal_connections', [
            'user_uuid' => $this->authUser->uuid,
            'guest_portal_theme_preset' => 'bold_modern',
            'guest_portal_primary_color' => '#1B4F8A',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSaveDirectWebsiteContentWithPageTitleBlankClears(): void
    {
        $this->authenticate();

        $this->patchJson('/api/v1/booking-portals/direct-website/content', [
            'pageTitle' => '',
        ])->assertOk();

        $this->assertDatabaseHas('booking_portal_connections', [
            'user_uuid' => $this->authUser->uuid,
            'guest_portal_page_title' => null,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testConnectAlreadyConnectedReturnsMessage(): void
    {
        $this->authenticate();
        $this->seedPortalRows();
        $this->postJson('/api/v1/booking-portals/airbnb/connect')->assertOk();

        $this->postJson('/api/v1/booking-portals/airbnb/connect')
            ->assertOk()
            ->assertJsonPath('message', 'This channel is already connected.');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSaveDirectWebsiteContentMergesLayout(): void
    {
        $this->authenticate();

        $this->patchJson('/api/v1/booking-portals/direct-website/content', [
            'headline' => 'Welcome',
            'message' => 'Stay with us',
            'layout' => [
                'businessName' => 'Acme',
                'amenities' => ['WiFi', 'Pool'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('booking_portal_connections', [
            'user_uuid' => $this->authUser->uuid,
            'portal_key' => 'direct_website',
            'guest_portal_headline' => 'Welcome',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSetDirectWebsiteLiveRequiresHeadline(): void
    {
        $this->authenticate();

        \App\Models\BookingPortalConnection::create([
            'user_uuid' => $this->authUser->uuid,
            'portal_key' => 'direct_website',
            'is_connected' => false,
            'is_active' => false,
            'listing_count' => 0,
            'guest_portal_design_completed' => true,
            'guest_portal_headline' => '   ',
            'guest_portal_message' => 'Welcome',
        ]);

        $this->patchJson('/api/v1/booking-portals/direct-website/live', [
            'live' => true,
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'Add a headline in the micro-site designer before going live.']);
    }


}
