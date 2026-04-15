<?php

namespace App\Http\Requests;

use App\Models\SymptomDailyLog;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSymptomDailyLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SymptomDailyLog $symptomDailyLog */
        $symptomDailyLog = $this->route('symptom_daily_log');

        return (int) $this->user()->id === (int) $symptomDailyLog->user_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'trend' => ['nullable', 'in:better,same,worse'],
            'fatigue' => ['nullable', 'in:good,baseline,bad'],
            'dizziness' => ['nullable', 'in:none,low,high'],
            'max_pain' => ['nullable', 'integer', 'min:0', 'max:10'],
            'expected_updated_at' => ['nullable', 'date'],
        ];
    }
}
