<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_portal_connections', function (Blueprint $table): void {
            $table->uuid('uuid')->primary()->unique()->index();
            $table->uuid('user_uuid')->index();
            $table->string('portal_key', 64)->index();
            $table->boolean('is_connected')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('listing_count')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('has_sync_issue')->default(false);
            $table->boolean('guest_portal_live')->default(false);
            $table->boolean('guest_portal_design_completed')->default(false);
            $table->string('guest_portal_headline', 512)->nullable();
            $table->text('guest_portal_message')->nullable();
            $table->string('guest_portal_page_title', 160)->nullable();
            $table->string('guest_portal_theme_preset', 48)->nullable();
            $table->string('guest_portal_primary_color', 16)->nullable();
            $table->string('guest_portal_accent_color', 16)->nullable();
            $table->string('guest_portal_hero_image_url', 2048)->nullable();
            $table->json('guest_portal_layout')->nullable();
            $table->timestamps();

            $table->unique(['user_uuid', 'portal_key']);
            $table->index(['created_at', 'updated_at'], 'booking_portal_timestamp_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_portal_connections');
    }
};
