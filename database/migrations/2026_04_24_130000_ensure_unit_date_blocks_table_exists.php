<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs environments where the migration batch was recorded but the table was never created
 * (e.g. interrupted migrate, restored DB, or different server state).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('unit_date_blocks')) {
            return;
        }

        Schema::create('unit_date_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('label', 255);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date']);
            $table->index(['unit_id', 'start_date']);
        });
    }

    public function down(): void
    {
        //
    }
};
