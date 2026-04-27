<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_date_blocks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('unit_id')->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('label', 255)->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date']);
            $table->index(['unit_id', 'start_date']);
            $table->index(['created_at', 'updated_at'], 'unit_date_blocks_timestamp_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_date_blocks');
    }
};
