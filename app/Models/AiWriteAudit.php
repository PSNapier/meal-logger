<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'domain',
    'target_type',
    'target_id',
    'log_date',
    'before_payload',
    'after_payload',
    'prompt',
    'assistant_summary',
])]
class AiWriteAudit extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'before_payload' => 'array',
            'after_payload' => 'array',
        ];
    }
}
