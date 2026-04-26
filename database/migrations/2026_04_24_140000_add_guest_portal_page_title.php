<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_portal_connections', function (Blueprint $table) {
            $table->string('guest_portal_page_title', 160)->nullable()->after('guest_portal_message');
        });
    }

    public function down(): void
    {
        Schema::table('booking_portal_connections', function (Blueprint $table) {
            $table->dropColumn('guest_portal_page_title');
        });
    }
};
