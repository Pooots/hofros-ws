<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SystemHealthService
{
    public function summary(): array
    {
        return [
            'app' => config('app.name'),
            'environment' => app()->environment(),
            'database' => $this->databaseStatus(),
        ];
    }

    private function databaseStatus(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'connected' => true,
                'driver' => config('database.default'),
            ];
        } catch (\Throwable $exception) {
            return [
                'connected' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }
}
