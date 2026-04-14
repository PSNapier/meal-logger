<?php

namespace App\Http\Requests;

use App\Models\DailyLog;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDailyLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DailyLog $dailyLog */
        $dailyLog = $this->route('daily_log');

        return (int) $this->user()->id === (int) $dailyLog->user_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'eating_window_start' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'eating_window_end' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'weight_lbs' => ['nullable', 'string', 'max:12'],
        ];
    }
}
