<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VertexOcrResult extends Model
{
    /** @use HasFactory<\Database\Factories\VertexOcrResultFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'image_name',
        'image_path',
        'mime_type',
        'image_size',
        'types',
        'text',
        'provider',
        'raw_response',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'types' => 'array',
            'raw_response' => 'array',
        ];
    }
}
