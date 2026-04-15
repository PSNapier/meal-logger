<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\Measurement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ?int $year = null, ?int $month = null): Response
    {
        $year ??= (int) now()->year;
        $month ??= (int) now()->month;

        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $logs = DailyLog::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->with(['mealItems', 'chatMessages' => fn ($q) => $q->orderBy('id')])
            ->get()
            ->keyBy(fn (DailyLog $log) => $log->date->toDateString());

        $measurements = Measurement::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (Measurement $measurement) => $measurement->date->toDateString());

        $days = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $log = $logs->get($key);
            $measurement = $measurements->get($key);
            $days[] = [
                'date' => $key,
                'day_name' => strtoupper($cursor->format('D')),
                'daily_log' => $log ? $this->serializeDailyLog($log, $measurement) : null,
            ];
        }

        return Inertia::render('Dashboard', [
            'year' => $year,
            'month' => $month,
            'month_label' => $start->format('F Y'),
            'days' => $days,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDailyLog(DailyLog $log, ?Measurement $measurement): array
    {
        return [
            'id' => $log->id,
            'date' => $log->date->toDateString(),
            'water_oz' => $log->water_oz !== null ? (float) $log->water_oz : null,
            'fiber_g' => $log->fiber_g !== null ? (float) $log->fiber_g : null,
            'calories' => $log->calories,
            'eating_window_start' => $this->formatTimeColumn($log->getAttributes()['eating_window_start'] ?? null),
            'eating_window_end' => $this->formatTimeColumn($log->getAttributes()['eating_window_end'] ?? null),
            'weight_lbs' => $measurement?->weight_lbs !== null
                ? (float) $measurement->weight_lbs
                : ($log->weight_lbs !== null ? (float) $log->weight_lbs : null),
            'measurement_updated_at' => $measurement?->updated_at?->toIso8601String(),
            'meal_items' => $log->mealItems->map(fn ($m) => [
                'id' => $m->id,
                'description' => $m->description,
                'calories' => (int) $m->calories,
                'protein_g' => (float) $m->protein_g,
                'carbs_g' => (float) $m->carbs_g,
                'fat_g' => (float) $m->fat_g,
                'sugar_g' => (float) $m->sugar_g,
                'fiber_g' => (float) $m->fiber_g,
                'water_oz' => (float) $m->water_oz,
            ])->values()->all(),
            'chat_messages' => $log->chatMessages->map(fn ($c) => [
                'id' => $c->id,
                'role' => $c->role,
                'content' => $c->content,
            ])->values()->all(),
        ];
    }

    private function formatTimeColumn(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $s = (string) $value;

        return strlen($s) >= 5 ? substr($s, 0, 5) : $s;
    }
}
