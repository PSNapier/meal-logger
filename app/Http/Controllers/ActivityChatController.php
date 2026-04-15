<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActivityChatRequest;
use App\Models\ActivityDailyLog;
use App\Models\AiWriteAudit;
use App\Models\ChatMessage;
use App\Services\ActivityLogExtractor;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class ActivityChatController extends Controller
{
    public function __invoke(StoreActivityChatRequest $request, ActivityLogExtractor $extractor): RedirectResponse
    {
        $user = $request->user();
        $logDate = $request->validated('log_date');
        $message = $request->validated('message');

        $dailyLog = ActivityDailyLog::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'date' => $logDate,
            ],
            [
                'total_sessions' => 0,
                'total_minutes' => 0,
                'calories_burned' => 0,
            ],
        );

        ChatMessage::query()->create([
            'user_id' => $user->id,
            'daily_log_id' => null,
            'domain' => 'activity',
            'log_date' => $logDate,
            'role' => 'user',
            'content' => $message,
        ]);

        $existing = [
            'total_sessions' => $dailyLog->total_sessions,
            'total_minutes' => $dailyLog->total_minutes,
            'calories_burned' => $dailyLog->calories_burned,
        ];

        try {
            $data = $extractor->extract($logDate, $message, $existing);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'message' => 'Could not reach activity model. Try again.',
            ]);
        }

        if ($data['log_date'] !== $logDate) {
            return back()->withErrors([
                'message' => 'Model returned a different date than selected.',
            ]);
        }

        DB::transaction(function () use ($dailyLog, $data, $user, $message): void {
            $locked = ActivityDailyLog::query()->whereKey($dailyLog->id)->lockForUpdate()->firstOrFail();
            $before = [
                'total_sessions' => $locked->total_sessions,
                'total_minutes' => $locked->total_minutes,
                'calories_burned' => $locked->calories_burned,
            ];

            $locked->update([
                'total_sessions' => max(0, (int) $data['total_sessions']),
                'total_minutes' => max(0, (int) $data['total_minutes']),
                'calories_burned' => max(0, (int) $data['calories_burned']),
            ]);

            ChatMessage::query()->create([
                'user_id' => $user->id,
                'daily_log_id' => null,
                'domain' => 'activity',
                'log_date' => $locked->date->toDateString(),
                'role' => 'assistant',
                'content' => (string) $data['assistant_summary'],
            ]);

            AiWriteAudit::query()->create([
                'user_id' => $user->id,
                'domain' => 'activity',
                'target_type' => ActivityDailyLog::class,
                'target_id' => $locked->id,
                'log_date' => $locked->date->toDateString(),
                'before_payload' => $before,
                'after_payload' => [
                    'total_sessions' => $locked->total_sessions,
                    'total_minutes' => $locked->total_minutes,
                    'calories_burned' => $locked->calories_burned,
                ],
                'prompt' => $message,
                'assistant_summary' => (string) $data['assistant_summary'],
            ]);
        });

        $parsed = Carbon::parse($logDate);

        return redirect()->route('activity-dashboard', [
            'year' => $parsed->year,
            'month' => $parsed->month,
        ]);
    }
}
