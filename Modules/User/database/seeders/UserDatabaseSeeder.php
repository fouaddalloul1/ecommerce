<?php

namespace Modules\User\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\User\Models\User;
class UserDatabaseSeeder extends Seeder
{
public function run(): void
    {
        // Create 3 test users using the model factory
        // This requires newFactory() in the User model (Option A above)
        User::factory()->count(3)->create();

        // Optionally create a known admin/test user
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => bcrypt('secret123'),
            ]
        );
    }
}
