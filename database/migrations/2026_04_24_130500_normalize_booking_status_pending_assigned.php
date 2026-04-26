<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bookings')->whereIn('status', ['new_booking'])->update(['status' => 'pending']);
        DB::table('bookings')->whereIn('status', ['accepted', 'confirmed'])->update(['status' => 'assigned']);
        DB::statement("UPDATE bookings SET status = 'pending' WHERE status IS NULL OR TRIM(status) = ''");
        DB::statement("ALTER TABLE bookings MODIFY status VARCHAR(32) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::table('bookings')->where('status', 'pending')->update(['status' => 'new_booking']);
        DB::table('bookings')->where('status', 'assigned')->update(['status' => 'confirmed']);
        DB::statement("ALTER TABLE bookings MODIFY status VARCHAR(32) NOT NULL DEFAULT 'new_booking'");
    }
};
