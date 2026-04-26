<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bookings')->where('status', 'accept')->update(['status' => 'accepted']);
    }

    public function down(): void
    {
        DB::table('bookings')->where('status', 'accepted')->update(['status' => 'accept']);
    }
};
