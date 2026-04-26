<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public function up(): void
    {
        Schema::table('unit_rate_intervals', function (Blueprint $table) {
            $table->boolean('closed_to_arrival')->default(false)->after('max_los');
            $table->boolean('closed_to_departure')->default(false)->after('closed_to_arrival');
            $table->json('day_prices')->nullable()->after('days_of_week');
        });

        $rows = DB::table('unit_rate_intervals')->select('id', 'base_price', 'days_of_week')->get();
        foreach ($rows as $row) {
            $base = (float) $row->base_price;
            $days = json_decode($row->days_of_week, true);
            if (! is_array($days)) {
                $days = [];
            }
            $prices = [];
            foreach (self::DAY_KEYS as $key) {
                $prices[$key] = ! empty($days[$key]) ? $base : 0.0;
            }
            DB::table('unit_rate_intervals')->where('id', $row->id)->update([
                'day_prices' => json_encode($prices),
                'closed_to_arrival' => false,
                'closed_to_departure' => false,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('unit_rate_intervals', function (Blueprint $table) {
            $table->dropColumn(['closed_to_arrival', 'closed_to_departure', 'day_prices']);
        });
    }
};
