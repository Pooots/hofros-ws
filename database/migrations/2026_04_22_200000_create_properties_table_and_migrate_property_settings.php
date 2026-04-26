<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('properties')) {
            return;
        }

        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('property_name');
            $table->string('contact_email');
            $table->string('phone', 64)->nullable();
            $table->string('currency', 16)->default('PHP');
            $table->string('check_in_time', 8)->default('14:00');
            $table->string('check_out_time', 8)->default('11:00');
            $table->timestamps();

            $table->index('user_id');
        });

        if (Schema::hasTable('property_settings')) {
            $rows = DB::table('property_settings')->get();
            foreach ($rows as $row) {
                DB::table('properties')->insert([
                    'user_id' => $row->user_id,
                    'property_name' => $row->property_name,
                    'contact_email' => $row->contact_email,
                    'phone' => $row->phone,
                    'currency' => $row->currency,
                    'check_in_time' => $row->check_in_time,
                    'check_out_time' => $row->check_out_time,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }

            Schema::drop('property_settings');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
