<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_portal_connections', function (Blueprint $table): void {
            $table->boolean('guest_portal_live')->default(false)->after('has_sync_issue');
            $table->boolean('guest_portal_design_completed')->default(false)->after('guest_portal_live');
            $table->string('guest_portal_headline', 512)->nullable()->after('guest_portal_design_completed');
            $table->text('guest_portal_message')->nullable()->after('guest_portal_headline');
        });
    }

    public function down(): void
    {
        Schema::table('booking_portal_connections', function (Blueprint $table): void {
            $table->dropColumn([
                'guest_portal_live',
                'guest_portal_design_completed',
                'guest_portal_headline',
                'guest_portal_message',
            ]);
        });
    }
};
