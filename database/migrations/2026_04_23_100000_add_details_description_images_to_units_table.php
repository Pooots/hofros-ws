<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('details', 500)->nullable()->after('name');
            $table->text('description')->nullable()->after('details');
            $table->json('images')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['details', 'description', 'images']);
        });
    }
};
