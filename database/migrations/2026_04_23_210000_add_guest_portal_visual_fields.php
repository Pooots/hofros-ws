<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_portal_connections', function (Blueprint $table): void {
            $table->string('guest_portal_theme_preset', 48)->nullable()->after('guest_portal_message');
            $table->string('guest_portal_primary_color', 16)->nullable()->after('guest_portal_theme_preset');
            $table->string('guest_portal_accent_color', 16)->nullable()->after('guest_portal_primary_color');
            $table->string('guest_portal_hero_image_url', 2048)->nullable()->after('guest_portal_accent_color');
        });
    }

    public function down(): void
    {
        Schema::table('booking_portal_connections', function (Blueprint $table): void {
            $table->dropColumn([
                'guest_portal_theme_preset',
                'guest_portal_primary_color',
                'guest_portal_accent_color',
                'guest_portal_hero_image_url',
            ]);
        });
    }
};
