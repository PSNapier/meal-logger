<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSymptomsChatRequest;
use App\Models\AiWriteAudit;
use App\Models\ChatMessage;
use App\Models\SymptomDailyLog;
use App\Services\SymptomsLogExtractor;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class SymptomsChatController extends Controller
{
    public function __invoke(StoreSymptomsChatRequest $request, SymptomsLogExtractor $extractor): RedirectResponse
    {
        $user = $request->user();
        $logDate = $request->validated('log_date');
        $message = $request->validated('message');

        $dailyLog = SymptomDailyLog::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'date' => $logDate,
            ],
            [
                'trend' => null,
                'fatigue' => null,
                'dizziness' => null,
                'max_pain' => null,
            ],
        );

        ChatMessage::query()->create([
            'user_id' => $user->id,
            'daily_log_id' => null,
            'domain' => 'symptoms',
            'log_date' => $logDate,
            'role' => 'user',
            'content' => $message,
        ]);

        $existing = [
            'trend' => $dailyLog->trend,
            'fatigue' => $dailyLog->fatigue,
            'dizziness' => $dailyLog->dizziness,
            'max_pain' => $dailyLog->max_pain,
        ];

        try {
            $data = $extractor->extract($logDate, $message, $existing);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'message' => 'Could not reach symptoms model. Try again.',
            ]);
        }

        if ($data['log_date'] !== $logDate) {
            return back()->withErrors([
                'message' => 'Model returned a different date than selected.',
            ]);
        }

        DB::transaction(function () use ($dailyLog, $data, $user, $message): void {
            $locked = SymptomDailyLog::query()->whereKey($dailyLog->id)->lockForUpdate()->firstOrFail();
            $before = [
                'trend' => $locked->trend,
                'fatigue' => $locked->fatigue,
                'dizziness' => $locked->dizziness,
                'max_pain' => $locked->max_pain,
            ];

            $trend = in_array($data['trend'], ['better', 'same', 'worse'], true) ? $data['trend'] : null;
            $fatigue = in_array($data['fatigue'], ['good', 'baseline', 'bad'], true) ? $data['fatigue'] : null;
            $dizziness = in_array($data['dizziness'], ['none', 'low', 'high'], true) ? $data['dizziness'] : null;
            $maxPain = $data['max_pain'];
            $maxPain = is_int($maxPain) && $maxPain >= 0 && $maxPain <= 10 ? $maxPain : null;

            $locked->update([
                'trend' => $trend,
                'fatigue' => $fatigue,
                'dizziness' => $dizziness,
                'max_pain' => $maxPain,
            ]);

            ChatMessage::query()->create([
                'user_id' => $user->id,
                'daily_log_id' => null,
                'domain' => 'symptoms',
                'log_date' => $locked->date->toDateString(),
                'role' => 'assistant',
                'content' => (string) $data['assistant_summary'],
            ]);

            AiWriteAudit::query()->create([
                'user_id' => $user->id,
                'domain' => 'symptoms',
                'target_type' => SymptomDailyLog::class,
                'target_id' => $locked->id,
                'log_date' => $locked->date->toDateString(),
                'before_payload' => $before,
                'after_payload' => [
                    'trend' => $locked->trend,
                    'fatigue' => $locked->fatigue,
                    'dizziness' => $locked->dizziness,
                    'max_pain' => $locked->max_pain,
                ],
                'prompt' => $message,
                'assistant_summary' => (string) $data['assistant_summary'],
            ]);
        });

        $parsed = Carbon::parse($logDate);

        return redirect()->route('symptoms-dashboard', [
            'year' => $parsed->year,
            'month' => $parsed->month,
        ]);
    }
}
