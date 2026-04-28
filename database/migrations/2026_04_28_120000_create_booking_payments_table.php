<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_payments', function (Blueprint $table): void {
            $table->uuid('uuid')->primary()->unique()->index();
            $table->uuid('booking_uuid')->index();
            $table->uuid('user_uuid')->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 16);
            $table->string('payment_type', 32)->index();
            $table->string('transaction_kind', 16)->default('payment');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['booking_uuid', 'created_at']);
            $table->index(['created_at', 'updated_at'], 'booking_payments_timestamp_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
    }
};
