<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CsvExportChannel extends Model
{
    /** @use HasFactory<\Database\Factories\CsvExportChannelFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'code',
        'name',
        'measurement',
        'timestamp_source',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CsvExportTask::class, 'channel_id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(CsvExportChannelTag::class, 'channel_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CsvExportChannelField::class, 'channel_id');
    }
}
