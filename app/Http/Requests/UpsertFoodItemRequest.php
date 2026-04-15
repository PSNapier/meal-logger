<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertFoodItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:32'],
            'unit_dimension' => ['required', 'string', 'in:mass,volume,count,other'],
            'unit_quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'calories_per_unit' => ['required', 'integer', 'min:0', 'max:1000000'],
            'protein_g_per_unit' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'carbs_g_per_unit' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'fat_g_per_unit' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'sugar_g_per_unit' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'fiber_g_per_unit' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'water_oz_per_unit' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ];
    }
}
