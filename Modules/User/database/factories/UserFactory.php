<?php
namespace Modules\User\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\User\Models\User;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        $name = $this->faker->name();
        $email = $this->faker->unique()->safeEmail();

        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $email,
            'email_verified_at' => now(),
            'password' => bcrypt('password'), // change for dev convenience
            'remember_token' => Str::random(10),
        ];
    }
}
