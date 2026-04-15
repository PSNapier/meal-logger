<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\SymptomDailyLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SymptomsDashboardController extends Controller
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
                'trend' => null,
                'fatigue' => null,
                'dizziness' => null,
                'max_pain' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        SymptomDailyLog::query()->upsert(
            $seedRows,
            ['user_id', 'date'],
            ['updated_at'],
        );

        $symptomLogs = SymptomDailyLog::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (SymptomDailyLog $log) => $log->date->toDateString());

        $messages = ChatMessage::query()
            ->where('user_id', $request->user()->id)
            ->where('domain', 'symptoms')
            ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('id')
            ->get()
            ->groupBy(fn (ChatMessage $message) => $message->log_date?->toDateString() ?? '');

        $days = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $log = $symptomLogs->get($key);
            $dayMessages = $messages->get($key, collect());

            $days[] = [
                'date' => $key,
                'day_name' => strtoupper($cursor->format('D')),
                'symptom_log' => $log ? [
                    'id' => $log->id,
                    'date' => $log->date->toDateString(),
                    'trend' => $log->trend,
                    'fatigue' => $log->fatigue,
                    'dizziness' => $log->dizziness,
                    'max_pain' => $log->max_pain,
                    'updated_at' => $log->updated_at?->toIso8601String(),
                ] : null,
                'chat_messages' => $dayMessages->map(fn (ChatMessage $message) => [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                ])->values()->all(),
            ];
        }

        return Inertia::render('SymptomsDashboard', [
            'year' => $year,
            'month' => $month,
            'month_label' => $start->format('F Y'),
            'days' => $days,
        ]);
    }
}
