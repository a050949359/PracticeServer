<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleOAuthAccount extends Model
{
    /** @use HasFactory<\Database\Factories\GoogleOAuthAccountFactory> */
    use HasFactory;

    protected $table = 'google_oauth_accounts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'provider_user_id',
        'email',
        'access_token',
        'refresh_token',
        'token_type',
        'scope',
        'access_token_expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
