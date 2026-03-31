<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvExportTask extends Model
{
    /** @use HasFactory<\Database\Factories\CsvExportTaskFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'template_id',
        'channel_id',
        'status',
        'file_name',
        'file_path',
        'disk',
        'total_rows',
        'generated_rows',
        'last_influx_imported_row',
        'last_error',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'generated_rows' => 'integer',
            'last_influx_imported_row' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CsvExportTemplate::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(CsvExportChannel::class);
    }
}
