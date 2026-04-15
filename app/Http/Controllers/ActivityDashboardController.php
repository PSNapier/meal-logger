<?php

namespace App\Http\Controllers;

use App\Models\ActivityDailyLog;
use App\Models\ChatMessage;
use App\Models\Measurement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityDashboardController extends Controller
{
    public function __invoke(Request $request, ?int $year = null, ?int $month = null): Response
    {
        $year ??= (int) now()->year;
        $month ??= (int) now()->month;

        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $seedRows = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $seedRows[] = [
                'user_id' => $request->user()->id,
                'date' => $cursor->toDateString(),
                'total_sessions' => 0,
                'total_minutes' => 0,
                'calories_burned' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        ActivityDailyLog::query()->upsert(
            $seedRows,
            ['user_id', 'date'],
            ['updated_at'],
        );

        $activityLogs = ActivityDailyLog::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (ActivityDailyLog $log) => $log->date->toDateString());

        $measurements = Measurement::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (Measurement $measurement) => $measurement->date->toDateString());

        $messages = ChatMessage::query()
            ->where('user_id', $request->user()->id)
            ->where('domain', 'activity')
            ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('id')
            ->get()
            ->groupBy(fn (ChatMessage $message) => $message->log_date?->toDateString() ?? '');

        $days = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $activity = $activityLogs->get($key);
            $measurement = $measurements->get($key);
            $dayMessages = $messages->get($key, collect());

            $days[] = [
                'date' => $key,
                'day_name' => strtoupper($cursor->format('D')),
                'activity_log' => $activity ? [
                    'id' => $activity->id,
                    'date' => $activity->date->toDateString(),
                    'total_sessions' => $activity->total_sessions,
                    'total_minutes' => $activity->total_minutes,
                    'calories_burned' => $activity->calories_burned,
                    'updated_at' => $activity->updated_at?->toIso8601String(),
                ] : null,
                'measurement' => $measurement ? [
                    'id' => $measurement->id,
                    'weight_lbs' => $measurement->weight_lbs !== null ? (float) $measurement->weight_lbs : null,
                    'updated_at' => $measurement->updated_at?->toIso8601String(),
                ] : null,
                'chat_messages' => $dayMessages->map(fn (ChatMessage $message) => [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                ])->values()->all(),
            ];
        }

        return Inertia::render('ActivityDashboard', [
            'year' => $year,
            'month' => $month,
            'month_label' => $start->format('F Y'),
            'days' => $days,
        ]);
    }
}
