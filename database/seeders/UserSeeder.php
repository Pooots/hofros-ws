<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'merchant_name' => 'Test Merchant',
            'first_name' => 'Test',
            'middle_name' => 'User',
            'last_name' => 'Test',
            'contact_number' => '1234567890',
            'address' => '1234567890',
            'email' => 'test@hofros.com',
            'password' => Hash::make('hofros@2026'),
        ]);
    }
}
