<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DebugResetDayController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(config('app.debug'), 404);

        $validated = $request->validate([
            'log_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $user = $request->user();
        $logDate = $validated['log_date'];

        $dailyLog = DailyLog::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $logDate)
            ->first();

        if ($dailyLog !== null) {
            DB::transaction(function () use ($dailyLog): void {
                $dailyLog->chatMessages()->delete();
                $dailyLog->mealItems()->delete();
                $dailyLog->delete();
            });
        }

        $parsed = Carbon::parse($logDate);

        return redirect()->route('dashboard', [
            'year' => $parsed->year,
            'month' => $parsed->month,
        ]);
    }
}
