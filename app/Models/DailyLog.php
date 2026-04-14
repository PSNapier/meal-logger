<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'date',
    'water_oz',
    'fiber_g',
    'calories',
    'eating_window_start',
    'eating_window_end',
    'weight_lbs',
])]
class DailyLog extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mealItems(): HasMany
    {
        return $this->hasMany(MealItem::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'water_oz' => 'decimal:2',
            'fiber_g' => 'decimal:2',
            'calories' => 'integer',
            'weight_lbs' => 'decimal:1',
        ];
    }
}
