<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSymptomDailyLogRequest;
use App\Models\SymptomDailyLog;
use Illuminate\Http\RedirectResponse;

class SymptomDailyLogController extends Controller
{
    public function update(UpdateSymptomDailyLogRequest $request, SymptomDailyLog $symptomDailyLog): RedirectResponse
    {
        $validated = $request->validated();
        $expected = $validated['expected_updated_at'] ?? null;

        if ($expected !== null) {
            $current = $symptomDailyLog->updated_at?->toIso8601String();
            if ($current !== $expected) {
                return back()->withErrors([
                    'message' => 'This symptom row changed in another session. Refresh and retry.',
                ]);
            }
        }

        $symptomDailyLog->fill([
            'trend' => $validated['trend'] ?? null,
            'fatigue' => $validated['fatigue'] ?? null,
            'dizziness' => $validated['dizziness'] ?? null,
            'max_pain' => $validated['max_pain'] ?? null,
        ]);
        $symptomDailyLog->save();

        return back();
    }
}
