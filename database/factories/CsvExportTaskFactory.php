<?php

namespace Database\Factories;

use App\Models\CsvExportTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CsvExportTask>
 */
class CsvExportTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => CsvExportTask::STATUS_PENDING,
            'file_name' => now()->format('Ymd_His').'.csv',
            'file_path' => 'exports/csv/'.fake()->uuid().'.csv',
            'disk' => 'local',
            'template_id' => null,
            'channel_id' => null,
            'total_rows' => 5,
            'generated_rows' => 0,
            'last_influx_imported_row' => 0,
            'last_error' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }
}
