<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE units MODIFY type VARCHAR(64) NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE units SET type = '' WHERE type IS NULL");
        DB::statement("ALTER TABLE units MODIFY type VARCHAR(64) NOT NULL DEFAULT ''");
    }
};
