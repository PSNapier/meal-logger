<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'log_date' => ['required', 'date_format:Y-m-d'],
            'weight_lbs' => ['nullable', 'string', 'max:12'],
            'expected_updated_at' => ['nullable', 'date'],
        ];
    }
}
