<?php

namespace App\Services;

use App\Models\FoodItem;
use App\Models\User;

class FoodLibraryUpserter
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsert(User $user, array $payload, string $source = 'user_manual', ?FoodItem $existing = null): FoodItem
    {
        $name = (string) ($payload['name'] ?? '');
        $normalizedName = FoodLibraryMatcher::normalizeName($name);

        $attributes = [
            'name' => $name,
            'normalized_name' => $normalizedName,
            'unit' => (string) ($payload['unit'] ?? 'oz'),
            'unit_dimension' => (string) ($payload['unit_dimension'] ?? 'mass'),
            'unit_quantity' => (float) ($payload['unit_quantity'] ?? 1),
            'calories_per_unit' => (int) ($payload['calories_per_unit'] ?? 0),
            'protein_g_per_unit' => (float) ($payload['protein_g_per_unit'] ?? 0),
            'carbs_g_per_unit' => (float) ($payload['carbs_g_per_unit'] ?? 0),
            'fat_g_per_unit' => (float) ($payload['fat_g_per_unit'] ?? 0),
            'sugar_g_per_unit' => (float) ($payload['sugar_g_per_unit'] ?? 0),
            'fiber_g_per_unit' => (float) ($payload['fiber_g_per_unit'] ?? 0),
            'water_oz_per_unit' => (float) ($payload['water_oz_per_unit'] ?? 0),
            'source' => $source,
        ];

        if ($existing !== null) {
            $existing->fill($attributes);
            $existing->user_id = $user->id;
            $existing->save();

            return $existing->refresh();
        }

        return FoodItem::query()->updateOrCreate(
            ['user_id' => $user->id, 'normalized_name' => $normalizedName],
            $attributes,
        );
    }
}

