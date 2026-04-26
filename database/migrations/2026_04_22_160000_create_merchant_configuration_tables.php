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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('property_name');
            $table->string('contact_email');
            $table->string('phone', 64)->nullable();
            $table->string('currency', 16)->default('PHP');
            $table->string('check_in_time', 8)->default('14:00');
            $table->string('check_out_time', 8)->default('11:00');
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 64);
            $table->unsignedSmallInteger('max_guests')->default(1);
            $table->decimal('price_per_night', 10, 2);
            $table->string('currency', 16)->default('PHP');
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('new_booking')->default(true);
            $table->boolean('cancellation')->default(true);
            $table->boolean('check_in')->default(true);
            $table->boolean('check_out')->default(false);
            $table->boolean('payment')->default(true);
            $table->boolean('review')->default(false);
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('role', 32)->default('staff');
            $table->timestamps();

            $table->index(['owner_user_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('units');
        Schema::dropIfExists('property_settings');
    }
};
