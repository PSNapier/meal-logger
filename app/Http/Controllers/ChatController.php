<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatRequest;
use App\Models\ChatMessage;
use App\Models\DailyLog;
use App\Services\NutritionLogExtractor;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class ChatController extends Controller
{
    public function __invoke(
        StoreChatRequest $request,
        NutritionLogExtractor $extractor,
    ): RedirectResponse {
        $user = $request->user();
        $logDate = $request->validated('log_date');
        $message = $request->validated('message');

        $dailyLog = DailyLog::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'date' => $logDate,
            ],
            [
                'water_oz' => null,
                'fiber_g' => null,
                'calories' => null,
                'eating_window_start' => null,
                'eating_window_end' => null,
                'weight_lbs' => null,
            ],
        );

        ChatMessage::query()->create([
            'user_id' => $user->id,
            'daily_log_id' => $dailyLog->id,
            'domain' => 'food',
            'log_date' => $logDate,
            'role' => 'user',
            'content' => $message,
        ]);

        $dailyLog->load('mealItems');

        $existingDay = null;
        if (
            $dailyLog->mealItems->isNotEmpty()
            || $dailyLog->calories !== null
            || $dailyLog->water_oz !== null
            || $dailyLog->fiber_g !== null
        ) {
            $existingDay = [
                'calories' => $dailyLog->calories,
                'water_oz' => $dailyLog->water_oz !== null ? (float) $dailyLog->water_oz : null,
                'fiber_g' => $dailyLog->fiber_g !== null ? (float) $dailyLog->fiber_g : null,
                'items' => $dailyLog->mealItems->map(fn ($m) => [
                    'id' => $m->id,
                    'description' => (string) $m->description,
                    'calories' => (int) $m->calories,
                    'protein_g' => (float) $m->protein_g,
                    'carbs_g' => (float) $m->carbs_g,
                    'fat_g' => (float) $m->fat_g,
                    'sugar_g' => (float) $m->sugar_g,
                    'fiber_g' => (float) $m->fiber_g,
                    'water_oz' => (float) $m->water_oz,
                ])->values()->all(),
            ];
        }

        try {
            $data = $extractor->extract($logDate, $message, $existingDay);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'message' => 'Could not reach the nutrition model. Check API keys and try again.',
            ]);
        }

        if ($data['log_date'] !== $logDate) {
            return back()->withErrors([
                'message' => 'Model returned a different date than selected. Try again with an explicit date in your message.',
            ]);
        }

        DB::transaction(function () use ($dailyLog, $data, $user): void {
            $lockedLog = DailyLog::query()
                ->whereKey($dailyLog->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedLog->mealItems()->delete();

            $totals = [
                'calories' => 0,
                'water_oz' => 0.0,
                'fiber_g' => 0.0,
            ];

            foreach ($data['items'] as $item) {
                $description = trim((string) ($item['description'] ?? ''));
                if ($description === '') {
                    continue;
                }

                $calories = (int) ($item['calories'] ?? 0);
                $proteinG = (float) ($item['protein_g'] ?? 0);
                $carbsG = (float) ($item['carbs_g'] ?? 0);
                $fatG = (float) ($item['fat_g'] ?? 0);
                $sugarG = (float) ($item['sugar_g'] ?? 0);
                $fiberG = (float) ($item['fiber_g'] ?? 0);
                $waterOz = (float) ($item['water_oz'] ?? 0);

                $lockedLog->mealItems()->create([
                    'description' => $description,
                    'calories' => $calories,
                    'protein_g' => $proteinG,
                    'carbs_g' => $carbsG,
                    'fat_g' => $fatG,
                    'sugar_g' => $sugarG,
                    'fiber_g' => $fiberG,
                    'water_oz' => $waterOz,
                ]);

                $totals['calories'] += $calories;
                $totals['fiber_g'] += $fiberG;
                $totals['water_oz'] += $waterOz;
            }

            $lockedLog->update([
                'water_oz' => round($totals['water_oz'], 2),
                'fiber_g' => round($totals['fiber_g'], 2),
                'calories' => (int) $totals['calories'],
            ]);

            $assistantContent = (string) $data['assistant_summary'];

            ChatMessage::query()->create([
                'user_id' => $user->id,
                'daily_log_id' => $lockedLog->id,
                'domain' => 'food',
                'log_date' => $lockedLog->date->toDateString(),
                'role' => 'assistant',
                'content' => $assistantContent,
            ]);
        });

        $parsed = Carbon::parse($logDate);

        return redirect()->route('dashboard', [
            'year' => $parsed->year,
            'month' => $parsed->month,
        ]);
    }
}
