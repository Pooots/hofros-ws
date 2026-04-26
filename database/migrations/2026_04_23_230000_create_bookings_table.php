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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_phone', 64);
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('adults')->default(1);
            $table->unsignedSmallInteger('children')->default(0);
            $table->decimal('total_price', 12, 2);
            $table->string('currency', 16);
            $table->string('source', 32);
            $table->string('status', 32)->default('new_booking');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'check_in']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
