<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_rate_intervals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('unit_id')->index();
            $table->string('name')->nullable()->index();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->integer('min_los')->nullable();
            $table->integer('max_los')->nullable();
            $table->boolean('closed_to_arrival')->default(false);
            $table->boolean('closed_to_departure')->default(false);
            $table->json('day_prices')->nullable();
            $table->json('days_of_week')->nullable();
            $table->decimal('base_price', 12, 2)->default(0);
            $table->string('currency', 16)->default('PHP');
            $table->timestamps();

            $table->index(['unit_id', 'start_date']);
            $table->index(['created_at', 'updated_at'], 'unit_rate_intervals_timestamp_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_rate_intervals');
    }
};
