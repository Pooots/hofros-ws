<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('property_settings')->where('currency', 'EUR')->update(['currency' => 'PHP']);
        DB::table('units')->where('currency', 'EUR')->update(['currency' => 'PHP']);
    }

    public function down(): void
    {
        // Intentionally empty: we cannot know which rows were EUR before conversion.
    }
};
