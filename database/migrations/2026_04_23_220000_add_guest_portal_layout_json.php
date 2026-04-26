<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_portal_connections', function (Blueprint $table): void {
            $table->json('guest_portal_layout')->nullable()->after('guest_portal_hero_image_url');
        });
    }

    public function down(): void
    {
        Schema::table('booking_portal_connections', function (Blueprint $table): void {
            $table->dropColumn('guest_portal_layout');
        });
    }
};
