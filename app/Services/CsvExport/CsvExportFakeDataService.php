<?php

namespace App\Services\CsvExport;

use Illuminate\Support\Str;

class CsvExportFakeDataService
{
    /**
     * @return array<string, string>
     */
    public function availableColumns(): array
    {
        $columns = [];

        foreach ($this->availableColumnDefinitions() as $name => $definition) {
            $columns[$name] = $definition['label'];
        }

        return $columns;
    }

    /**
     * @return array<string, array{label: string, recommended_role: string, data_type: string}>
     */
    public function availableColumnDefinitions(): array
    {
        return [
            'serial_no' => [
                'label' => 'Serial No',
                'recommended_role' => 'field',
                'data_type' => 'int',
            ],
            'uuid' => [
                'label' => 'UUID',
                'recommended_role' => 'tag',
                'data_type' => 'string',
            ],
            'name' => [
                'label' => 'Name',
                'recommended_role' => 'tag',
                'data_type' => 'string',
            ],
            'email' => [
                'label' => 'Email',
                'recommended_role' => 'tag',
                'data_type' => 'string',
            ],
            'phone' => [
                'label' => 'Phone',
                'recommended_role' => 'tag',
                'data_type' => 'string',
            ],
            'company' => [
                'label' => 'Company',
                'recommended_role' => 'tag',
                'data_type' => 'string',
            ],
            'city' => [
                'label' => 'City',
                'recommended_role' => 'tag',
                'data_type' => 'string',
            ],
            'address' => [
                'label' => 'Address',
                'recommended_role' => 'field',
                'data_type' => 'string',
            ],
            'status' => [
                'label' => 'Status',
                'recommended_role' => 'tag',
                'data_type' => 'string',
            ],
            'created_at' => [
                'label' => 'Created At',
                'recommended_role' => 'field',
                'data_type' => 'string',
            ],
            'temperature_c' => [
                'label' => 'Temperature (C)',
                'recommended_role' => 'field',
                'data_type' => 'float',
            ],
            'humidity_pct' => [
                'label' => 'Humidity (%)',
                'recommended_role' => 'field',
                'data_type' => 'float',
            ],
            'voltage_v' => [
                'label' => 'Voltage (V)',
                'recommended_role' => 'field',
                'data_type' => 'float',
            ],
            'current_a' => [
                'label' => 'Current (A)',
                'recommended_role' => 'field',
                'data_type' => 'float',
            ],
            'power_kw' => [
                'label' => 'Power (kW)',
                'recommended_role' => 'field',
                'data_type' => 'float',
            ],
            'energy_kwh' => [
                'label' => 'Energy (kWh)',
                'recommended_role' => 'field',
                'data_type' => 'float',
            ],
            'quantity' => [
                'label' => 'Quantity',
                'recommended_role' => 'field',
                'data_type' => 'int',
            ],
            'score' => [
                'label' => 'Score',
                'recommended_role' => 'field',
                'data_type' => 'int',
            ],
            'amount' => [
                'label' => 'Amount',
                'recommended_role' => 'field',
                'data_type' => 'float',
            ],
            'is_online' => [
                'label' => 'Is Online',
                'recommended_role' => 'field',
                'data_type' => 'bool',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function availableColumnsForRole(string $role): array
    {
        $columns = [];

        foreach ($this->availableColumnDefinitions() as $name => $definition) {
            if ($definition['recommended_role'] !== $role) {
                continue;
            }

            $columns[$name] = $definition['label'];
        }

        return $columns;
    }

    /**
     * @param  list<string>  $columns
     * @param  array<string, list<string>>  $valueOverrides
     * @return list<string>
     */
    public function generateRow(array $columns, int $sequence, array $valueOverrides = []): array
    {
        return array_map(fn (string $column): string => $this->generateValue($column, $sequence, $valueOverrides[$column] ?? []), $columns);
    }

    /**
     * @param  list<string>  $allowedValues
     */
    private function generateValue(string $column, int $sequence, array $allowedValues = []): string
    {
        if ($allowedValues !== []) {
            return (string) fake()->randomElement($allowedValues);
        }

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
            'temperature_c' => number_format(fake()->randomFloat(2, 18, 35), 2, '.', ''),
            'humidity_pct' => number_format(fake()->randomFloat(2, 30, 95), 2, '.', ''),
            'voltage_v' => number_format(fake()->randomFloat(2, 100, 240), 2, '.', ''),
            'current_a' => number_format(fake()->randomFloat(3, 0.1, 15), 3, '.', ''),
            'power_kw' => number_format(fake()->randomFloat(3, 0.05, 8), 3, '.', ''),
            'energy_kwh' => number_format(fake()->randomFloat(3, 0.5, 120), 3, '.', ''),
            'quantity' => (string) fake()->numberBetween(1, 500),
            'score' => (string) fake()->numberBetween(0, 100),
            'amount' => number_format(fake()->randomFloat(2, 10, 5000), 2, '.', ''),
            'is_online' => fake()->boolean() ? 'true' : 'false',
            default => '',
        };
    }
}
