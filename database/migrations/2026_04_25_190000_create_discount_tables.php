<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('discount_value', 10, 2);
            $table->unsignedInteger('min_nights')->default(1);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['user_id', 'code']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('unit_discounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->enum('discount_type', ['early_bird', 'long_stay', 'last_minute', 'weekend_discount', 'date_range']);
            $table->decimal('discount_percent', 5, 2);
            $table->unsignedInteger('min_days_in_advance')->nullable();
            $table->unsignedInteger('min_nights')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_discounts');
        Schema::dropIfExists('promo_codes');
    }
};
