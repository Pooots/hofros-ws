<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table): void {
            $table->uuid('uuid')->primary()->unique()->index();
            $table->uuid('user_uuid')->index();
            $table->string('code', 64)->index();
            $table->string('discount_type', 50)->index();
            $table->decimal('discount_value', 12, 2);
            $table->integer('min_nights')->default(1);
            $table->integer('max_uses')->nullable();
            $table->integer('uses_count')->default(0);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_uuid', 'code']);
            $table->index(['user_uuid', 'status']);
            $table->index(['created_at', 'updated_at'], 'promo_codes_timestamp_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
