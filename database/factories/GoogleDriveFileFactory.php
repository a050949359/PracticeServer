<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GoogleDriveFile>
 */
class GoogleDriveFileFactory extends Factory
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
            'drive_file_id' => 'drive_'.fake()->uuid(),
            'folder_id' => 'folder_'.fake()->uuid(),
            'file_name' => fake()->lexify('sample-????').'.txt',
            'mime_type' => 'text/plain',
            'file_size' => fake()->numberBetween(100, 2000000),
            'web_view_link' => fake()->url(),
            'web_content_link' => fake()->url(),
            'provider' => 'google_drive',
        ];
    }
}
