<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::firstOrCreate(
            ['email' => 'admin@rsblitar.id'],
            [
                'name' => 'Administrator Monitoring',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );
    }
}

