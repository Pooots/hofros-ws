<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_portal_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('portal_key', 64);
            $table->boolean('is_connected')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('listing_count')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('has_sync_issue')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'portal_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_portal_connections');
    }
};
