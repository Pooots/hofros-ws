<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->unique()->index();
            $table->uuid('owner_user_uuid')->index();
            $table->string('name')->index();
            $table->string('email')->index();
            $table->string('role', 32)->default('staff');
            $table->timestamps();

            $table->index(['owner_user_uuid', 'email']);
            $table->index(['created_at', 'updated_at'], 'team_members_timestamp_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
