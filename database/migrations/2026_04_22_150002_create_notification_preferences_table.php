<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->boolean('new_booking')->default(true);
            $table->boolean('cancellation')->default(true);
            $table->boolean('check_in')->default(true);
            $table->boolean('check_out')->default(false);
            $table->boolean('payment')->default(true);
            $table->boolean('review')->default(false);
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['created_at', 'updated_at'], 'notification_preferences_timestamp_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
