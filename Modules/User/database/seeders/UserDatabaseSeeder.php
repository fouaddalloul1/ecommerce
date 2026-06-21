<?php

namespace Modules\User\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserDatabaseSeeder extends Seeder
{

    public function run(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            DB::table('users')->insert([
                'first_name' => "User{$i}",
                'last_name' => "Test{$i}",
                'email' => "user{$i}@test.com",
                'balance' => 100000 * $i,
                'password' => Hash::make('12345678'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
