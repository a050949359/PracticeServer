<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvExportChannelTag extends Model
{
    /** @use HasFactory<\Database\Factories\CsvExportChannelTagFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'channel_id',
        'column_name',
        'allowed_values',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_values' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(CsvExportChannel::class, 'channel_id');
    }
}
