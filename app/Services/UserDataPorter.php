<?php

namespace App\Services;

use App\Models\ActivityDailyLog;
use App\Models\DailyLog;
use App\Models\MealItem;
use App\Models\Measurement;
use App\Models\SymptomDailyLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UserDataPorter
{
    public const int VERSION = 1;

    public const int MAX_TOTAL_ROWS = 20000;

    public const string MODE_MERGE = 'merge';

    public const string MODE_OVERWRITE = 'overwrite';

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $user->load([
            'dailyLogs.mealItems',
            'activityDailyLogs',
            'symptomDailyLogs',
            'measurements',
        ]);

        return [
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
            'daily_logs' => $user->dailyLogs->sortBy('date')->values()->map(fn (DailyLog $log) => $this->serializeDailyLog($log))->all(),
            'activity_daily_logs' => $user->activityDailyLogs->sortBy('date')->values()->map(fn (ActivityDailyLog $log) => $this->serializeActivityDailyLog($log))->all(),
            'symptom_daily_logs' => $user->symptomDailyLogs->sortBy('date')->values()->map(fn (SymptomDailyLog $log) => $this->serializeSymptomDailyLog($log))->all(),
            'measurements' => $user->measurements->sortBy('date')->values()->map(fn (Measurement $m) => $this->serializeMeasurement($m))->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<string, int>>
     */
    public function summarize(User $user, array $payload): array
    {
        $this->validatePayload($payload);
        $this->assertRowCountWithinLimit($payload);

        $bucket = fn (): array => [
            'new' => 0,
            'identical' => 0,
            'conflicting' => 0,
            'blank' => 0,
            'updates' => 0,
        ];

        $summary = [
            'daily_logs' => $bucket(),
            'activity_daily_logs' => $bucket(),
            'symptom_daily_logs' => $bucket(),
            'measurements' => $bucket(),
        ];

        foreach ($this->dailyLogsFromPayload($payload) as $row) {
            $this->bucketDailyLog($user, $row, $summary, 'daily_logs');
        }

        foreach ($payload['activity_daily_logs'] as $row) {
            $this->bucketSimpleRow($user, ActivityDailyLog::class, $row, $summary, 'activity_daily_logs', [
                'total_sessions', 'total_minutes', 'calories_burned',
            ]);
        }

        foreach ($payload['symptom_daily_logs'] as $row) {
            $this->bucketSimpleRow($user, SymptomDailyLog::class, $row, $summary, 'symptom_daily_logs', [
                'trend', 'fatigue', 'dizziness', 'max_pain',
            ]);
        }

        foreach ($payload['measurements'] as $row) {
            $this->bucketSimpleRow($user, Measurement::class, $row, $summary, 'measurements', [
                'weight_lbs',
            ]);
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function import(User $user, array $payload, string $mode): void
    {
        if (! in_array($mode, [self::MODE_MERGE, self::MODE_OVERWRITE], true)) {
            throw new InvalidArgumentException('Invalid import mode.');
        }

        $this->validatePayload($payload);
        $this->assertRowCountWithinLimit($payload);

        DB::transaction(function () use ($user, $payload, $mode): void {
            foreach ($this->dailyLogsFromPayload($payload) as $row) {
                $this->importDailyLogRow($user, $row, $mode);
            }

            foreach ($payload['activity_daily_logs'] as $row) {
                $this->importSimpleRow(
                    $user,
                    ActivityDailyLog::class,
                    $row,
                    $mode,
                    ['total_sessions', 'total_minutes', 'calories_burned'],
                    fn (array $data) => new ActivityDailyLog($data),
                );
            }

            foreach ($payload['symptom_daily_logs'] as $row) {
                $this->importSimpleRow(
                    $user,
                    SymptomDailyLog::class,
                    $row,
                    $mode,
                    ['trend', 'fatigue', 'dizziness', 'max_pain'],
                    fn (array $data) => new SymptomDailyLog($data),
                );
            }

            foreach ($payload['measurements'] as $row) {
                $this->importSimpleRow(
                    $user,
                    Measurement::class,
                    $row,
                    $mode,
                    ['weight_lbs'],
                    fn (array $data) => new Measurement($data),
                );
            }
        });
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    public function decodeUploadedJson(string $contents, string $originalName = 'import.json'): array
    {
        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException("File [{$originalName}] is not valid JSON: {$e->getMessage()}");
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("File [{$originalName}] is not valid JSON.");
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function validatePayload(array $payload): void
    {
        $allowedTop = ['version', 'exported_at', 'user', 'daily_logs', 'activity_daily_logs', 'symptom_daily_logs', 'measurements'];
        $unknown = array_diff(array_keys($payload), $allowedTop);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown top-level keys: '.implode(', ', $unknown));
        }

        if (($payload['version'] ?? null) !== self::VERSION) {
            throw new InvalidArgumentException('Unsupported or missing export version.');
        }

        foreach (['daily_logs', 'activity_daily_logs', 'symptom_daily_logs', 'measurements'] as $key) {
            if (! isset($payload[$key]) || ! is_array($payload[$key])) {
                throw new InvalidArgumentException("Missing or invalid array: {$key}");
            }
        }

        foreach ($payload['daily_logs'] as $i => $row) {
            $this->assertAssocRow($row, "daily_logs.{$i}");
            $this->assertAllowedKeys($row, ['date', 'water_oz', 'fiber_g', 'calories', 'eating_window_start', 'eating_window_end', 'weight_lbs', 'meal_items'], "daily_logs.{$i}");
            $this->parseDate($row['date'] ?? null, "daily_logs.{$i}.date");

            $mealItems = $row['meal_items'] ?? [];

            if (! is_array($mealItems)) {
                throw new InvalidArgumentException("daily_logs.{$i}.meal_items must be an array.");
            }

            foreach ($mealItems as $j => $meal) {
                $this->assertAssocRow($meal, "daily_logs.{$i}.meal_items.{$j}");
                $this->assertAllowedKeys($meal, ['description', 'calories', 'protein_g', 'carbs_g', 'fat_g', 'sugar_g', 'fiber_g', 'water_oz'], "daily_logs.{$i}.meal_items.{$j}");
            }
        }

        foreach ($payload['activity_daily_logs'] as $i => $row) {
            $this->assertAssocRow($row, "activity_daily_logs.{$i}");
            $this->assertAllowedKeys($row, ['date', 'total_sessions', 'total_minutes', 'calories_burned'], "activity_daily_logs.{$i}");
            $this->parseDate($row['date'] ?? null, "activity_daily_logs.{$i}.date");
        }

        foreach ($payload['symptom_daily_logs'] as $i => $row) {
            $this->assertAssocRow($row, "symptom_daily_logs.{$i}");
            $this->assertAllowedKeys($row, ['date', 'trend', 'fatigue', 'dizziness', 'max_pain'], "symptom_daily_logs.{$i}");
            $this->parseDate($row['date'] ?? null, "symptom_daily_logs.{$i}.date");
        }

        foreach ($payload['measurements'] as $i => $row) {
            $this->assertAssocRow($row, "measurements.{$i}");
            $this->assertAllowedKeys($row, ['date', 'weight_lbs'], "measurements.{$i}");
            $this->parseDate($row['date'] ?? null, "measurements.{$i}.date");
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertRowCountWithinLimit(array $payload): void
    {
        $n = count($payload['daily_logs']);

        foreach ($payload['daily_logs'] as $row) {
            $n += count($row['meal_items'] ?? []);
        }

        $n += count($payload['activity_daily_logs']);
        $n += count($payload['symptom_daily_logs']);
        $n += count($payload['measurements']);

        if ($n > self::MAX_TOTAL_ROWS) {
            throw new InvalidArgumentException('Import exceeds maximum of '.self::MAX_TOTAL_ROWS.' rows (including meal items).');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function dailyLogsFromPayload(array $payload): array
    {
        return array_values($payload['daily_logs']);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $summary
     */
    private function bucketDailyLog(User $user, array $row, array &$summary, string $tableKey): void
    {
        $this->initSummaryBucket($summary, $tableKey);

        if ($this->isBlankDailyLogRow($row)) {
            $summary[$tableKey]['blank']++;

            return;
        }

        $date = $this->parseDate($row['date'], "{$tableKey}.date");
        $existing = DailyLog::query()->where('user_id', $user->id)->whereDate('date', $date)->first();

        if (! $existing) {
            $summary[$tableKey]['new']++;

            return;
        }

        if ($this->dailyLogConflicts($existing, $row)) {
            $summary[$tableKey]['conflicting']++;

            return;
        }

        if ($this->dailyLogIdenticalForMerge($existing, $row)) {
            $summary[$tableKey]['identical']++;

            return;
        }

        $summary[$tableKey]['updates']++;
    }

    /**
     * @param  class-string<ActivityDailyLog|SymptomDailyLog|Measurement>  $modelClass
     * @param  array<string, mixed>  $row
     * @param  list<string>  $domainKeys
     * @param  array<string, int>  $summary
     */
    private function bucketSimpleRow(User $user, string $modelClass, array $row, array &$summary, string $tableKey, array $domainKeys): void
    {
        $this->initSummaryBucket($summary, $tableKey);

        if ($this->isBlankSimpleRow($row, $domainKeys)) {
            $summary[$tableKey]['blank']++;

            return;
        }

        $date = $this->parseDate($row['date'], "{$tableKey}.date");
        $existing = $modelClass::query()->where('user_id', $user->id)->whereDate('date', $date)->first();

        if (! $existing) {
            $summary[$tableKey]['new']++;

            return;
        }

        if ($this->simpleScalarConflict($existing, $row, $domainKeys)) {
            $summary[$tableKey]['conflicting']++;

            return;
        }

        if ($this->simpleIdenticalForMerge($existing, $row, $domainKeys)) {
            $summary[$tableKey]['identical']++;

            return;
        }

        $summary[$tableKey]['updates']++;
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function initSummaryBucket(array &$summary, string $tableKey): void
    {
        if (! isset($summary[$tableKey])) {
            $summary[$tableKey] = [
                'new' => 0,
                'identical' => 0,
                'conflicting' => 0,
                'blank' => 0,
                'updates' => 0,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isBlankDailyLogRow(array $row): bool
    {
        $scalarsBlank = $this->areDailyScalarsBlank($row);
        $items = $this->normalizedMealItemsFromRow($row);

        return $scalarsBlank && $items === [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function areDailyScalarsBlank(array $row): bool
    {
        foreach (['water_oz', 'fiber_g', 'calories', 'eating_window_start', 'eating_window_end', 'weight_lbs'] as $k) {
            if (! $this->isBlankScalar($row[$k] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function normalizedMealItemsFromRow(array $row): array
    {
        $items = $row['meal_items'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        $out = [];

        foreach ($items as $meal) {
            if (! is_array($meal) || $this->isBlankMealItemRow($meal)) {
                continue;
            }

            $out[] = $this->normalizeMealItemForCompare($meal);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meal
     */
    private function isBlankMealItemRow(array $meal): bool
    {
        if (! $this->isBlankScalar($meal['description'] ?? null)) {
            return false;
        }

        foreach (['calories', 'protein_g', 'carbs_g', 'fat_g', 'sugar_g', 'fiber_g', 'water_oz'] as $k) {
            $v = $meal[$k] ?? null;

            if ($this->isBlankScalar($v)) {
                continue;
            }

            if ($this->isNumericZero($v)) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function isNumericZero(mixed $v): bool
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v === 0.0;
        }

        if (is_string($v) && is_numeric($v)) {
            return (float) $v === 0.0;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $meal
     * @return array<string, mixed>
     */
    private function normalizeMealItemForCompare(array $meal): array
    {
        return [
            'description' => trim((string) ($meal['description'] ?? '')),
            'calories' => (int) ($meal['calories'] ?? 0),
            'protein_g' => $this->roundDecimal($meal['protein_g'] ?? 0),
            'carbs_g' => $this->roundDecimal($meal['carbs_g'] ?? 0),
            'fat_g' => $this->roundDecimal($meal['fat_g'] ?? 0),
            'sugar_g' => $this->roundDecimal($meal['sugar_g'] ?? 0),
            'fiber_g' => $this->roundDecimal($meal['fiber_g'] ?? 0),
            'water_oz' => $this->roundDecimal($meal['water_oz'] ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizedMealItemsFromModel(DailyLog $log): array
    {
        $out = [];

        foreach ($log->mealItems as $meal) {
            $row = [
                'description' => $meal->description,
                'calories' => $meal->calories,
                'protein_g' => $meal->protein_g,
                'carbs_g' => $meal->carbs_g,
                'fat_g' => $meal->fat_g,
                'sugar_g' => $meal->sugar_g,
                'fiber_g' => $meal->fiber_g,
                'water_oz' => $meal->water_oz,
            ];

            if ($this->isBlankMealItemRow($row)) {
                continue;
            }

            $out[] = $this->normalizeMealItemForCompare($row);
        }

        usort($out, fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $a
     * @param  list<array<string, mixed>>  $b
     */
    private function mealArraysEqual(array $a, array $b): bool
    {
        $aa = $a;
        $bb = $b;
        usort($aa, fn ($x, $y) => strcmp(json_encode($x), json_encode($y)));
        usort($bb, fn ($x, $y) => strcmp(json_encode($x), json_encode($y)));

        return json_encode($aa) === json_encode($bb);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $domainKeys
     */
    private function isBlankSimpleRow(array $row, array $domainKeys): bool
    {
        foreach ($domainKeys as $k) {
            if (! $this->isBlankScalar($row[$k] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function isBlankScalar(mixed $v): bool
    {
        if ($v === null) {
            return true;
        }

        if (is_string($v) && trim($v) === '') {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function dailyLogConflicts(DailyLog $existing, array $row): bool
    {
        if ($this->dailyScalarConflict($existing, $row)) {
            return true;
        }

        $existingMeals = $this->normalizedMealItemsFromModel($existing);
        $incomingMeals = $this->normalizedMealItemsFromRow($row);

        if ($existingMeals !== [] && $incomingMeals !== [] && ! $this->mealArraysEqual($existingMeals, $incomingMeals)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function dailyScalarConflict(DailyLog $existing, array $row): bool
    {
        $map = [
            'water_oz' => $existing->water_oz,
            'fiber_g' => $existing->fiber_g,
            'calories' => $existing->calories,
            'eating_window_start' => $existing->eating_window_start,
            'eating_window_end' => $existing->eating_window_end,
            'weight_lbs' => $existing->weight_lbs,
        ];

        foreach ($map as $key => $existingVal) {
            $incomingVal = $row[$key] ?? null;

            if ($this->isBlankScalar($incomingVal)) {
                continue;
            }

            if ($this->isBlankScalar($existingVal)) {
                continue;
            }

            if (! $this->scalarValuesEqual($key, $existingVal, $incomingVal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function dailyLogIdenticalForMerge(DailyLog $existing, array $row): bool
    {
        if ($this->dailyLogConflicts($existing, $row)) {
            return false;
        }

        if ($this->mergeWouldChangeDailyLog($existing, $row)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mergeWouldChangeDailyLog(DailyLog $existing, array $row): bool
    {
        $map = [
            'water_oz' => $existing->water_oz,
            'fiber_g' => $existing->fiber_g,
            'calories' => $existing->calories,
            'eating_window_start' => $existing->eating_window_start,
            'eating_window_end' => $existing->eating_window_end,
            'weight_lbs' => $existing->weight_lbs,
        ];

        foreach ($map as $key => $existingVal) {
            $incomingVal = $row[$key] ?? null;

            if ($this->isBlankScalar($incomingVal)) {
                continue;
            }

            if ($this->isBlankScalar($existingVal)) {
                return true;
            }

            if (! $this->scalarValuesEqual($key, $existingVal, $incomingVal)) {
                return true;
            }
        }

        $existingMeals = $this->normalizedMealItemsFromModel($existing);
        $incomingMeals = $this->normalizedMealItemsFromRow($row);

        if ($existingMeals === [] && $incomingMeals !== []) {
            return true;
        }

        return false;
    }

    /**
     * @param  ActivityDailyLog|SymptomDailyLog|Measurement  $existing
     * @param  array<string, mixed>  $row
     * @param  list<string>  $domainKeys
     */
    private function simpleScalarConflict($existing, array $row, array $domainKeys): bool
    {
        foreach ($domainKeys as $key) {
            $existingVal = $existing->getAttribute($key);
            $incomingVal = $row[$key] ?? null;

            if ($this->isBlankScalar($incomingVal)) {
                continue;
            }

            if ($this->isBlankScalar($existingVal)) {
                continue;
            }

            if (! $this->scalarValuesEqual($key, $existingVal, $incomingVal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  ActivityDailyLog|SymptomDailyLog|Measurement  $existing
     * @param  array<string, mixed>  $row
     * @param  list<string>  $domainKeys
     */
    private function simpleIdenticalForMerge($existing, array $row, array $domainKeys): bool
    {
        if ($this->simpleScalarConflict($existing, $row, $domainKeys)) {
            return false;
        }

        return ! $this->mergeWouldChangeSimpleRow($existing, $row, $domainKeys);
    }

    /**
     * @param  ActivityDailyLog|SymptomDailyLog|Measurement  $existing
     * @param  array<string, mixed>  $row
     * @param  list<string>  $domainKeys
     */
    private function mergeWouldChangeSimpleRow($existing, array $row, array $domainKeys): bool
    {
        foreach ($domainKeys as $key) {
            $existingVal = $existing->getAttribute($key);
            $incomingVal = $row[$key] ?? null;

            if ($this->isBlankScalar($incomingVal)) {
                continue;
            }

            if ($this->isBlankScalar($existingVal)) {
                return true;
            }

            if (! $this->scalarValuesEqual($key, $existingVal, $incomingVal)) {
                return true;
            }
        }

        return false;
    }

    private function scalarValuesEqual(string $key, mixed $a, mixed $b): bool
    {
        if (in_array($key, ['water_oz', 'fiber_g', 'weight_lbs', 'protein_g', 'carbs_g', 'fat_g', 'sugar_g'], true)) {
            return abs((float) $a - (float) $b) < 0.0001;
        }

        if (in_array($key, ['calories', 'total_sessions', 'total_minutes', 'calories_burned', 'max_pain'], true)) {
            return (int) $a === (int) $b;
        }

        if (in_array($key, ['eating_window_start', 'eating_window_end', 'trend', 'fatigue', 'dizziness'], true)) {
            return $this->normalizeTimeOrString((string) $a) === $this->normalizeTimeOrString((string) $b);
        }

        return (string) $a === (string) $b;
    }

    private function normalizeTimeOrString(string $v): string
    {
        $v = trim($v);

        try {
            return Carbon::parse($v)->format('H:i:s');
        } catch (\Throwable) {
            return $v;
        }
    }

    private function roundDecimal(mixed $v): float
    {
        return round((float) $v, 4);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importDailyLogRow(User $user, array $row, string $mode): void
    {
        if ($this->isBlankDailyLogRow($row)) {
            return;
        }

        $date = $this->parseDate($row['date'], 'daily_logs.date');
        $existing = DailyLog::query()->where('user_id', $user->id)->whereDate('date', $date)->first();

        if (! $existing) {
            $log = new DailyLog([
                'user_id' => $user->id,
                'date' => $date,
            ]);
            $this->applyDailyScalarsMergeFill($log, $row);
            $log->save();
            $this->syncMealItems($log, $row, replace: true);

            return;
        }

        $conflicts = $this->dailyLogConflicts($existing, $row);

        if ($conflicts && $mode === self::MODE_MERGE) {
            return;
        }

        if (! $conflicts && $this->dailyLogIdenticalForMerge($existing, $row)) {
            return;
        }

        if ($conflicts && $mode === self::MODE_OVERWRITE) {
            $this->applyDailyScalarsOverwrite($existing, $row);
            $existing->save();
            $incomingMeals = $this->normalizedMealItemsFromRow($row);

            if ($incomingMeals !== []) {
                $existing->mealItems()->delete();
                $this->createMealItemsFromRow($existing, $row);
            }

            return;
        }

        if (! $conflicts) {
            $this->applyDailyScalarsMergeFill($existing, $row);
            $existing->save();

            $existingMeals = $this->normalizedMealItemsFromModel($existing);
            $incomingMeals = $this->normalizedMealItemsFromRow($row);

            if ($existingMeals === [] && $incomingMeals !== []) {
                $this->createMealItemsFromRow($existing, $row);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function applyDailyScalarsMergeFill(DailyLog $log, array $row): void
    {
        foreach (['water_oz', 'fiber_g', 'calories', 'eating_window_start', 'eating_window_end', 'weight_lbs'] as $key) {
            $incoming = $row[$key] ?? null;

            if ($this->isBlankScalar($incoming)) {
                continue;
            }

            $current = $log->getAttribute($key);

            if ($this->isBlankScalar($current)) {
                $log->setAttribute($key, $this->castDailyScalar($key, $incoming));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function applyDailyScalarsOverwrite(DailyLog $log, array $row): void
    {
        foreach (['water_oz', 'fiber_g', 'calories', 'eating_window_start', 'eating_window_end', 'weight_lbs'] as $key) {
            $incoming = $row[$key] ?? null;

            if ($this->isBlankScalar($incoming)) {
                continue;
            }

            $log->setAttribute($key, $this->castDailyScalar($key, $incoming));
        }
    }

    private function castDailyScalar(string $key, mixed $v): mixed
    {
        return match ($key) {
            'calories' => (int) $v,
            'eating_window_start', 'eating_window_end' => $this->castTimeValue($v),
            default => $v,
        };
    }

    private function castTimeValue(mixed $v): ?string
    {
        if ($this->isBlankScalar($v)) {
            return null;
        }

        try {
            return Carbon::parse((string) $v)->format('H:i:s');
        } catch (\Throwable) {
            return (string) $v;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function syncMealItems(DailyLog $log, array $row, bool $replace): void
    {
        if ($replace) {
            $log->mealItems()->delete();
        }

        $this->createMealItemsFromRow($log, $row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createMealItemsFromRow(DailyLog $log, array $row): void
    {
        foreach ($row['meal_items'] ?? [] as $meal) {
            if (! is_array($meal) || $this->isBlankMealItemRow($meal)) {
                continue;
            }

            MealItem::query()->create([
                'daily_log_id' => $log->id,
                'description' => trim((string) ($meal['description'] ?? '')) ?: '—',
                'calories' => (int) ($meal['calories'] ?? 0),
                'protein_g' => $meal['protein_g'] ?? 0,
                'carbs_g' => $meal['carbs_g'] ?? 0,
                'fat_g' => $meal['fat_g'] ?? 0,
                'sugar_g' => $meal['sugar_g'] ?? 0,
                'fiber_g' => $meal['fiber_g'] ?? 0,
                'water_oz' => $meal['water_oz'] ?? 0,
            ]);
        }
    }

    /**
     * @param  class-string<ActivityDailyLog|SymptomDailyLog|Measurement>  $modelClass
     */
    private function coerceSimpleFieldForCreate(string $modelClass, string $key, mixed $raw): mixed
    {
        if ($modelClass === ActivityDailyLog::class && in_array($key, ['total_sessions', 'total_minutes', 'calories_burned'], true)) {
            return $this->isBlankScalar($raw) ? 0 : (int) $raw;
        }

        return $this->isBlankScalar($raw) ? null : $raw;
    }

    /**
     * @param  class-string<ActivityDailyLog|SymptomDailyLog|Measurement>  $modelClass
     * @param  array<string, mixed>  $row
     * @param  list<string>  $domainKeys
     * @param  callable(array<string, mixed>): ActivityDailyLog|SymptomDailyLog|Measurement  $instantiate
     */
    private function importSimpleRow(User $user, string $modelClass, array $row, string $mode, array $domainKeys, callable $instantiate): void
    {
        if ($this->isBlankSimpleRow($row, $domainKeys)) {
            return;
        }

        $date = $this->parseDate($row['date'], 'date');
        $existing = $modelClass::query()->where('user_id', $user->id)->whereDate('date', $date)->first();

        if (! $existing) {
            $data = ['user_id' => $user->id, 'date' => $date];
            foreach ($domainKeys as $k) {
                $raw = $row[$k] ?? null;
                $data[$k] = $this->coerceSimpleFieldForCreate($modelClass, $k, $raw);
            }
            $model = $instantiate($data);
            $model->save();

            return;
        }

        $conflict = $this->simpleScalarConflict($existing, $row, $domainKeys);

        if ($conflict && $mode === self::MODE_MERGE) {
            return;
        }

        if (! $conflict && $this->simpleIdenticalForMerge($existing, $row, $domainKeys)) {
            return;
        }

        if ($conflict && $mode === self::MODE_OVERWRITE) {
            foreach ($domainKeys as $k) {
                $incoming = $row[$k] ?? null;

                if (! $this->isBlankScalar($incoming)) {
                    $existing->setAttribute($k, $incoming);
                }
            }
            $existing->save();

            return;
        }

        if (! $conflict) {
            foreach ($domainKeys as $k) {
                $incoming = $row[$k] ?? null;

                if ($this->isBlankScalar($incoming)) {
                    continue;
                }

                $current = $existing->getAttribute($k);

                if ($this->isBlankScalar($current)) {
                    $existing->setAttribute($k, $incoming);
                }
            }
            $existing->save();
        }
    }

    private function serializeDailyLog(DailyLog $log): array
    {
        return [
            'date' => $log->date->toDateString(),
            'water_oz' => $log->water_oz !== null ? (float) $log->water_oz : null,
            'fiber_g' => $log->fiber_g !== null ? (float) $log->fiber_g : null,
            'calories' => $log->calories,
            'eating_window_start' => $this->exportTime($log->eating_window_start),
            'eating_window_end' => $this->exportTime($log->eating_window_end),
            'weight_lbs' => $log->weight_lbs !== null ? (float) $log->weight_lbs : null,
            'meal_items' => $log->mealItems->map(fn (MealItem $m) => [
                'description' => $m->description,
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

    private function exportTime(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $v)->format('H:i:s');
        } catch (\Throwable) {
            return (string) $v;
        }
    }

    private function serializeActivityDailyLog(ActivityDailyLog $log): array
    {
        return [
            'date' => $log->date->toDateString(),
            'total_sessions' => (int) $log->total_sessions,
            'total_minutes' => (int) $log->total_minutes,
            'calories_burned' => (int) $log->calories_burned,
        ];
    }

    private function serializeSymptomDailyLog(SymptomDailyLog $log): array
    {
        return [
            'date' => $log->date->toDateString(),
            'trend' => $log->trend,
            'fatigue' => $log->fatigue,
            'dizziness' => $log->dizziness,
            'max_pain' => $log->max_pain,
        ];
    }

    private function serializeMeasurement(Measurement $m): array
    {
        return [
            'date' => $m->date->toDateString(),
            'weight_lbs' => $m->weight_lbs !== null ? (float) $m->weight_lbs : null,
        ];
    }

    private function parseDate(mixed $v, string $path): string
    {
        if ($v === null || (is_string($v) && trim($v) === '')) {
            throw new InvalidArgumentException("Invalid date at {$path}.");
        }

        try {
            return Carbon::parse((string) $v)->toDateString();
        } catch (\Throwable) {
            throw new InvalidArgumentException("Invalid date at {$path}.");
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function assertAssocRow(mixed $row, string $path): void
    {
        if (! is_array($row) || array_is_list($row)) {
            throw new InvalidArgumentException("Invalid row object at {$path}.");
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $allowed
     */
    private function assertAllowedKeys(array $row, array $allowed, string $path): void
    {
        $unknown = array_diff(array_keys($row), $allowed);

        if ($unknown !== []) {
            throw new InvalidArgumentException("Unknown keys at {$path}: ".implode(', ', $unknown));
        }
    }
}
