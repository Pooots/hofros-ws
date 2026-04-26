<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * External OTAs have no API integration yet — show as not connected for all existing accounts.
     */
    public function up(): void
    {
        DB::table('booking_portal_connections')
            ->whereIn('portal_key', ['airbnb', 'booking_com', 'expedia', 'vrbo', 'tripadvisor'])
            ->update([
                'is_connected' => false,
                'is_active' => false,
                'listing_count' => 0,
                'last_synced_at' => null,
                'has_sync_issue' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        //
    }
};
