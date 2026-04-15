<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMeasurementRequest;
use App\Models\DailyLog;
use App\Models\Measurement;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class MeasurementController extends Controller
{
    public function upsert(UpdateMeasurementRequest $request): RedirectResponse
    {
        $user = $request->user();
        $date = $request->validated('log_date');
        $rawWeight = (string) ($request->validated('weight_lbs') ?? '');
        $expectedUpdatedAt = $request->validated('expected_updated_at');

        $weight = null;
        $trimmedWeight = trim($rawWeight);
        if ($trimmedWeight !== '' && strcasecmp($trimmedWeight, 'n/a') !== 0 && is_numeric($trimmedWeight)) {
            $weight = (float) $trimmedWeight;
        }

        $conflict = false;

        DB::transaction(function () use ($user, $date, $weight, $expectedUpdatedAt, &$conflict): void {
            $measurement = Measurement::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $date)
                ->lockForUpdate()
                ->first();

            if ($measurement !== null && $expectedUpdatedAt !== null) {
                $current = $measurement->updated_at?->toIso8601String();
                if ($current !== $expectedUpdatedAt) {
                    $conflict = true;

                    return;
                }
            }

            $measurement ??= new Measurement([
                'user_id' => $user->id,
                'date' => $date,
            ]);
            $measurement->weight_lbs = $weight;
            $measurement->save();

            DailyLog::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $date)
                ->update(['weight_lbs' => $weight]);
        });

        if ($conflict) {
            return back()->withErrors([
                'message' => 'This weight row changed in another session. Refresh and retry.',
            ]);
        }

        $parsed = Carbon::parse($date);

        return back()->with('measurement_date', $parsed->toDateString());
    }
}
