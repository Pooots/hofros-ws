<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('property_name')->index();
            $table->string('contact_email')->index();
            $table->string('phone', 64)->nullable();
            $table->string('currency', 16)->default('PHP');
            $table->string('check_in_time', 8)->default('14:00');
            $table->string('check_out_time', 8)->default('11:00');
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['created_at', 'updated_at'], 'property_settings_timestamp_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_settings');
    }
};
