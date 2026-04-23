<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiQueryLog extends Model
{
    protected $fillable = [
        'user_id',
        'question',
        'query_spec',
        'success',
        'duration_ms',
        'error_message',
    ];

    protected $casts = [
        'query_spec' => 'array',
        'success' => 'boolean',
        'duration_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

