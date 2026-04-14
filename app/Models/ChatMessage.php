<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'daily_log_id',
    'role',
    'content',
])]
class ChatMessage extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyLog(): BelongsTo
    {
        return $this->belongsTo(DailyLog::class);
    }
}
