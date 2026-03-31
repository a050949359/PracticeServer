<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvExportChannelField extends Model
{
    /** @use HasFactory<\Database\Factories\CsvExportChannelFieldFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'channel_id',
        'column_name',
        'data_type',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(CsvExportChannel::class, 'channel_id');
    }
}
