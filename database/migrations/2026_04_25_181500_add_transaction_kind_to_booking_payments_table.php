<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('booking_payments', 'transaction_kind')) {
            Schema::table('booking_payments', function (Blueprint $table): void {
                $table->string('transaction_kind', 16)->default('payment')->after('payment_type');
            });
        }

        DB::table('booking_payments')
            ->whereNull('transaction_kind')
            ->update(['transaction_kind' => 'payment']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('booking_payments', 'transaction_kind')) {
            Schema::table('booking_payments', function (Blueprint $table): void {
                $table->dropColumn('transaction_kind');
            });
        }
    }
};
