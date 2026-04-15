<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatRequest;
use App\Models\ChatMessage;
use App\Models\DailyLog;
use App\Models\FoodItem;
use App\Services\FoodLibraryMatcher;
use App\Services\NutritionLogExtractor;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatController extends Controller
{
    public function __invoke(
        StoreChatRequest $request,
        NutritionLogExtractor $extractor,
        FoodLibraryMatcher $matcher,
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
            'role' => 'user',
            'content' => $message,
        ]);

        $dailyLog->load('mealItems.foodItem');

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
                'meal_items' => $dailyLog->mealItems->map(fn ($m) => [
                    'id' => $m->id,
                    'description' => $m->description,
                    'food_item_id' => $m->food_item_id ? (int) $m->food_item_id : null,
                    'quantity' => $m->quantity !== null ? (float) $m->quantity : null,
                    'unit' => $m->foodItem?->unit,
                ])->values()->all(),
            ];
        }

        $confirmedMatches = collect($matcher->match($user, $message))
            ->filter(fn (array $row) => $row['food_item'] !== null && (float) $row['confidence'] >= 0.8)
            ->map(fn (array $row) => [
                'food_item_id' => (int) $row['food_item']->id,
                'name' => (string) $row['food_item']->name,
                'unit' => (string) $row['food_item']->unit,
                'unit_dimension' => (string) $row['food_item']->unit_dimension,
                'confidence' => (float) $row['confidence'],
            ])
            ->values()
            ->all();

        try {
            $data = $extractor->extract($logDate, $message, $confirmedMatches, $existingDay);
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

            $foodItems = FoodItem::query()
                ->where('user_id', $user->id)
                ->whereIn('id', collect($data['meal_items'])->pluck('food_item_id')->filter()->all())
                ->get()
                ->keyBy('id');

            $lockedLog->mealItems()->delete();

            $totals = [
                'calories' => 0,
                'water_oz' => 0.0,
                'fiber_g' => 0.0,
            ];

            foreach ($data['meal_items'] as $item) {
                $foodItemId = isset($item['food_item_id']) ? (int) $item['food_item_id'] : null;
                if (! $foodItemId || ! $foodItems->has($foodItemId)) {
                    continue;
                }

                /** @var FoodItem $foodItem */
                $foodItem = $foodItems->get($foodItemId);
                $quantity = max(0.0, (float) ($item['quantity'] ?? 0));
                $providedUnit = strtolower((string) ($item['unit'] ?? $foodItem->unit));
                $foodUnit = strtolower((string) $foodItem->unit);

                if ($providedUnit !== '' && $foodUnit !== '' && $providedUnit !== $foodUnit) {
                    Log::info('Skipped meal item due to unsafe unit conversion request.', [
                        'user_id' => $user->id,
                        'daily_log_id' => $lockedLog->id,
                        'food_item_id' => $foodItemId,
                        'provided_unit' => $providedUnit,
                        'stored_unit' => $foodUnit,
                    ]);

                    continue;
                }

                $nutrition = $foodItem->nutritionAt($quantity);

                $lockedLog->mealItems()->create([
                    'food_item_id' => $foodItemId,
                    'description' => (string) ($item['description'] ?? $foodItem->name),
                    'quantity' => $quantity,
                    'calories' => $nutrition['calories'],
                    'protein_g' => $nutrition['protein_g'],
                    'carbs_g' => $nutrition['carbs_g'],
                    'fat_g' => $nutrition['fat_g'],
                    'sugar_g' => $nutrition['sugar_g'],
                    'fiber_g' => $nutrition['fiber_g'],
                    'water_oz' => $nutrition['water_oz'],
                ]);

                $totals['calories'] += $nutrition['calories'];
                $totals['fiber_g'] += $nutrition['fiber_g'];
                $totals['water_oz'] += $nutrition['water_oz'];
            }

            $lockedLog->update([
                'water_oz' => round($totals['water_oz'], 2),
                'fiber_g' => round($totals['fiber_g'], 2),
                'calories' => (int) $totals['calories'],
            ]);

            $unresolvedItems = collect($data['unresolved_items'] ?? [])
                ->map(fn (array $row) => trim((string) ($row['description'] ?? '')))
                ->filter()
                ->values()
                ->all();
            $assistantContent = (string) $data['assistant_summary'];
            if ($unresolvedItems !== []) {
                $assistantContent .= "\n\nUnresolved items:\n- ".implode("\n- ", $unresolvedItems);
            }

            ChatMessage::query()->create([
                'user_id' => $user->id,
                'daily_log_id' => $lockedLog->id,
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
