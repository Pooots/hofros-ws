<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_discounts', function (Blueprint $table): void {
            $table->uuid('uuid')->primary()->unique()->index();
            $table->uuid('user_uuid')->index();
            $table->uuid('unit_uuid')->index();
            $table->string('discount_type', 50)->index();
            $table->decimal('discount_percent', 12, 2);
            $table->integer('min_days_in_advance')->nullable();
            $table->integer('min_nights')->nullable();
            $table->date('valid_from')->nullable()->index();
            $table->date('valid_to')->nullable()->index();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->index(['user_uuid', 'status']);
            $table->index(['user_uuid', 'unit_uuid']);
            $table->index(['created_at', 'updated_at'], 'unit_discounts_timestamp_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_discounts');
    }
};
