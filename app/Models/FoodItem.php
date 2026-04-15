<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'normalized_name',
    'unit',
    'unit_dimension',
    'unit_quantity',
    'calories_per_unit',
    'protein_g_per_unit',
    'carbs_g_per_unit',
    'fat_g_per_unit',
    'sugar_g_per_unit',
    'fiber_g_per_unit',
    'water_oz_per_unit',
    'source',
])]
class FoodItem extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mealItems(): HasMany
    {
        return $this->hasMany(MealItem::class);
    }

    /**
     * @return array{calories:int,protein_g:float,carbs_g:float,fat_g:float,sugar_g:float,fiber_g:float,water_oz:float}
     */
    public function nutritionAt(float $quantity): array
    {
        $q = max($quantity, 0);

        return [
            'calories' => (int) round((float) $this->calories_per_unit * $q),
            'protein_g' => (float) $this->protein_g_per_unit * $q,
            'carbs_g' => (float) $this->carbs_g_per_unit * $q,
            'fat_g' => (float) $this->fat_g_per_unit * $q,
            'sugar_g' => (float) $this->sugar_g_per_unit * $q,
            'fiber_g' => (float) $this->fiber_g_per_unit * $q,
            'water_oz' => (float) $this->water_oz_per_unit * $q,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_quantity' => 'decimal:3',
            'calories_per_unit' => 'integer',
            'protein_g_per_unit' => 'decimal:3',
            'carbs_g_per_unit' => 'decimal:3',
            'fat_g_per_unit' => 'decimal:3',
            'sugar_g_per_unit' => 'decimal:3',
            'fiber_g_per_unit' => 'decimal:3',
            'water_oz_per_unit' => 'decimal:3',
        ];
    }
}
