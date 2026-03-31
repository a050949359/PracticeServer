<?php

namespace App\Services\CsvExport;

use App\Models\CsvExportChannel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CsvExportChannelService
{
    public function __construct(private CsvExportFakeDataService $csvExportFakeDataService) {}

    /**
     * @return array{available_columns: array<string, string>, available_tag_columns: array<string, string>, available_field_columns: array<string, string>, items: list<array<string, mixed>>}
     */
    public function listForUser(User $user): array
    {
        return [
            'available_columns' => $this->csvExportFakeDataService->availableColumns(),
            'available_tag_columns' => $this->csvExportFakeDataService->availableColumnsForRole('tag'),
            'available_field_columns' => $this->csvExportFakeDataService->availableColumnsForRole('field'),
            'items' => CsvExportChannel::query()
                ->with(['tags', 'fields'])
                ->where('user_id', $user->id)
                ->latest()
                ->get()
                ->map(fn (CsvExportChannel $channel): array => $this->serializeChannel($channel))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createForUser(User $user, array $validated): CsvExportChannel
    {
        return DB::transaction(function () use ($user, $validated): CsvExportChannel {
            $channel = CsvExportChannel::query()->create([
                'user_id' => $user->id,
                'code' => $validated['code'],
                'name' => $validated['name'],
                'measurement' => $validated['measurement'],
                'timestamp_source' => $validated['timestamp_source'] ?? 'now',
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            $this->syncTagsAndFields($channel, $validated['tags'] ?? [], $validated['fields'] ?? []);

            return $channel->load(['tags', 'fields']);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(CsvExportChannel $channel, array $validated): CsvExportChannel
    {
        return DB::transaction(function () use ($channel, $validated): CsvExportChannel {
            $channel->fill([
                'code' => $validated['code'] ?? $channel->code,
                'name' => $validated['name'] ?? $channel->name,
                'measurement' => $validated['measurement'] ?? $channel->measurement,
                'timestamp_source' => $validated['timestamp_source'] ?? $channel->timestamp_source,
                'is_active' => array_key_exists('is_active', $validated)
                    ? (bool) $validated['is_active']
                    : (bool) $channel->is_active,
            ])->save();

            if (array_key_exists('tags', $validated) || array_key_exists('fields', $validated)) {
                $this->syncTagsAndFields(
                    $channel,
                    $validated['tags'] ?? $channel->tags()->orderBy('sort_order')->get()->map(fn ($item): array => $item->only(['column_name', 'allowed_values', 'sort_order']))->all(),
                    $validated['fields'] ?? $channel->fields()->orderBy('sort_order')->get()->map(fn ($item): array => $item->only(['column_name', 'data_type', 'sort_order']))->all(),
                );
            }

            return $channel->load(['tags', 'fields']);
        });
    }

    public function delete(CsvExportChannel $channel): void
    {
        $channel->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $tags
     * @param  list<array<string, mixed>>  $fields
     */
    public function syncTagsAndFields(CsvExportChannel $channel, array $tags, array $fields): void
    {
        $channel->tags()->delete();
        $channel->fields()->delete();

        $tagRows = [];
        foreach ($tags as $index => $tag) {
            $tagRows[] = [
                'column_name' => (string) ($tag['column_name'] ?? ''),
                'allowed_values' => $this->normalizeAllowedValues($tag['allowed_values'] ?? []),
                'sort_order' => (int) ($tag['sort_order'] ?? $index),
            ];
        }

        if ($tagRows !== []) {
            $channel->tags()->createMany($tagRows);
        }

        $fieldRows = [];
        foreach ($fields as $index => $field) {
            $fieldRows[] = [
                'column_name' => (string) ($field['column_name'] ?? ''),
                'data_type' => (string) ($field['data_type'] ?? 'string'),
                'sort_order' => (int) ($field['sort_order'] ?? $index),
            ];
        }

        if ($fieldRows !== []) {
            $channel->fields()->createMany($fieldRows);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeChannel(CsvExportChannel $channel): array
    {
        $tags = $channel->tags
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($tag): array => [
                'id' => $tag->id,
                'column_name' => $tag->column_name,
                'allowed_values' => $this->normalizeAllowedValues($tag->allowed_values ?? []),
                'sort_order' => $tag->sort_order,
            ])
            ->all();

        $fields = $channel->fields
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($field): array => [
                'id' => $field->id,
                'column_name' => $field->column_name,
                'data_type' => $field->data_type,
                'sort_order' => $field->sort_order,
            ])
            ->all();

        return [
            'id' => $channel->id,
            'code' => $channel->code,
            'name' => $channel->name,
            'measurement' => $channel->measurement,
            'timestamp_source' => $channel->timestamp_source,
            'is_active' => (bool) $channel->is_active,
            'tags' => $tags,
            'fields' => $fields,
            'created_at' => $channel->created_at?->toISOString(),
            'updated_at' => $channel->updated_at?->toISOString(),
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeAllowedValues(mixed $allowedValues): array
    {
        if (! is_array($allowedValues)) {
            return [];
        }

        $normalizedValues = [];

        foreach ($allowedValues as $value) {
            $normalizedValue = trim((string) $value);

            if ($normalizedValue === '') {
                continue;
            }

            $normalizedValues[] = $normalizedValue;
        }

        return array_values(array_unique($normalizedValues));
    }
}
