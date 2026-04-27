<?php

use App\Models\Unit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('property_id')->index();
            $table->string('name')->index();
            $table->string('details', 500)->nullable();
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->string('type', 64)->index();
            $table->integer('max_guests')->default(1);
            $table->integer('bedrooms')->nullable();
            $table->integer('beds')->nullable();
            $table->decimal('price_per_night', 12, 2);
            $table->string('currency', 16)->default('PHP');
            $table->string('status', 32)->default('active');
            $table->json('week_schedule')->nullable()->default(json_encode(Unit::defaultWeekSchedule()));
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['created_at', 'updated_at'], 'units_timestamp_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
