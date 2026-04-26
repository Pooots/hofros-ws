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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('min_los')->nullable();
            $table->unsignedInteger('max_los')->nullable();
            $table->json('days_of_week');
            $table->decimal('base_price', 10, 2)->default(0);
            $table->string('currency', 16)->default('PHP');
            $table->timestamps();

            $table->index(['unit_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_rate_intervals');
    }
};
