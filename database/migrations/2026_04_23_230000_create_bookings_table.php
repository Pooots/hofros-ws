<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('unit_id')->index();
            $table->string('reference', 40)->unique();
            $table->string('guest_name', 255)->nullable()->index();
            $table->string('guest_email', 255)->nullable()->index();
            $table->string('guest_phone', 64)->nullable();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('adults')->default(1);
            $table->unsignedSmallInteger('children')->default(0);
            $table->decimal('total_price', 12, 2);
            $table->string('currency', 16);
            $table->string('source', 32);
            $table->string('status', 32)->default('new_booking');
            $table->text('notes')->nullable();
            $table->uuid('portal_batch_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'check_in']);
            $table->index(['created_at', 'updated_at'], 'bookings_timestamp_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
