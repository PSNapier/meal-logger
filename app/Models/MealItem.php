<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'daily_log_id',
    'food_item_id',
    'description',
    'quantity',
    'calories',
    'protein_g',
    'carbs_g',
    'fat_g',
    'sugar_g',
    'fiber_g',
    'water_oz',
])]
class MealItem extends Model
{
    public function dailyLog(): BelongsTo
    {
        return $this->belongsTo(DailyLog::class);
    }

    public function foodItem(): BelongsTo
    {
        return $this->belongsTo(FoodItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calories' => 'integer',
            'quantity' => 'decimal:3',
            'protein_g' => 'decimal:2',
            'carbs_g' => 'decimal:2',
            'fat_g' => 'decimal:2',
            'sugar_g' => 'decimal:2',
            'fiber_g' => 'decimal:2',
            'water_oz' => 'decimal:2',
        ];
    }
}
