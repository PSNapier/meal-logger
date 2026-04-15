<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDailyLogRequest;
use App\Models\DailyLog;
use App\Models\Measurement;
use Illuminate\Http\RedirectResponse;

class DailyLogController extends Controller
{
    public function update(UpdateDailyLogRequest $request, DailyLog $dailyLog): RedirectResponse
    {
        $validated = $request->validated();

        $dailyLog->eating_window_start = $validated['eating_window_start'] ?? null;
        $dailyLog->eating_window_end = $validated['eating_window_end'] ?? null;

        if (array_key_exists('weight_lbs', $validated)) {
            $raw = $validated['weight_lbs'];
            if ($raw === null || $raw === '') {
                $dailyLog->weight_lbs = null;
            } elseif (is_string($raw) && strcasecmp(trim($raw), 'n/a') === 0) {
                $dailyLog->weight_lbs = null;
            } elseif (is_numeric($raw)) {
                $dailyLog->weight_lbs = (float) $raw;
            }

            Measurement::query()->updateOrCreate(
                [
                    'user_id' => $dailyLog->user_id,
                    'date' => $dailyLog->date->toDateString(),
                ],
                [
                    'weight_lbs' => $dailyLog->weight_lbs,
                ],
            );
        }

        $dailyLog->save();

        return back();
    }
}
