<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GoogleOAuthAccount>
 */
class GoogleOAuthAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider_user_id' => 'google_'.fake()->uuid(),
            'email' => fake()->safeEmail(),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_type' => 'Bearer',
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'access_token_expires_at' => now()->addHour(),
        ];
    }
}
