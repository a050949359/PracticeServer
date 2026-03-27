<?php

namespace App\Services\Export;

use Illuminate\Support\Str;

class CsvExportFakeDataService
{
    /**
     * @return array<string, string>
     */
    public function availableColumns(): array
    {
        return [
            'serial_no' => 'Serial No',
            'uuid' => 'UUID',
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'company' => 'Company',
            'city' => 'City',
            'address' => 'Address',
            'status' => 'Status',
            'created_at' => 'Created At',
        ];
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    public function generateRow(array $columns, int $sequence): array
    {
        return array_map(fn (string $column): string => $this->generateValue($column, $sequence), $columns);
    }

    private function generateValue(string $column, int $sequence): string
    {
        return match ($column) {
            'serial_no' => (string) $sequence,
            'uuid' => (string) Str::uuid(),
            'name' => fake()->name(),
            'email' => sprintf('user%04d_%s@example.test', $sequence, Str::lower(Str::random(6))),
            'phone' => fake()->phoneNumber(),
            'company' => fake()->company(),
            'city' => fake()->city(),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'status' => fake()->randomElement(['draft', 'queued', 'processing', 'done']),
            'created_at' => now()->format('Y-m-d H:i:s'),
            default => '',
        };
    }
}
