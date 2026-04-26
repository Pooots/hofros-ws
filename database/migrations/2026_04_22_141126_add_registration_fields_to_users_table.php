<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('merchant_name')->after('id');
            $table->string('first_name')->after('merchant_name');
            $table->string('last_name')->after('first_name');
            $table->string('contact_number', 30)->after('last_name');
            $table->string('address')->after('contact_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'merchant_name',
                'first_name',
                'last_name',
                'contact_number',
                'address',
            ]);
        });
    }
};
