<?php

use App\Models\ActivityDailyLog;
use App\Models\DailyLog;
use App\Models\Measurement;
use App\Models\SymptomDailyLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('meal-logger:backfill-domain-logs {--chunk=500}', function () {
    $chunkSize = max(100, (int) $this->option('chunk'));
    $processed = 0;

    $this->info("Backfill starting. Chunk size: {$chunkSize}");

    DailyLog::query()
        ->orderBy('id')
        ->chunkById($chunkSize, function ($logs) use (&$processed): void {
            $measurementRows = [];
            $activityRows = [];
            $symptomRows = [];

            foreach ($logs as $log) {
                $date = $log->date->toDateString();
                $now = now();

                $measurementRows[] = [
                    'user_id' => $log->user_id,
                    'date' => $date,
                    'weight_lbs' => $log->weight_lbs !== null ? (float) $log->weight_lbs : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $activityRows[] = [
                    'user_id' => $log->user_id,
                    'date' => $date,
                    'total_sessions' => 0,
                    'total_minutes' => 0,
                    'calories_burned' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $symptomRows[] = [
                    'user_id' => $log->user_id,
                    'date' => $date,
                    'trend' => null,
                    'fatigue' => null,
                    'dizziness' => null,
                    'max_pain' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($measurementRows !== []) {
                Measurement::query()->upsert(
                    $measurementRows,
                    ['user_id', 'date'],
                    ['weight_lbs', 'updated_at'],
                );
            }

            if ($activityRows !== []) {
                ActivityDailyLog::query()->upsert(
                    $activityRows,
                    ['user_id', 'date'],
                    ['updated_at'],
                );
            }

            if ($symptomRows !== []) {
                SymptomDailyLog::query()->upsert(
                    $symptomRows,
                    ['user_id', 'date'],
                    ['updated_at'],
                );
            }

            $processed += count($measurementRows);
            $this->line("Processed {$processed} daily rows...");
        });

    // Ensure legacy food chat history has explicit domain/date metadata.
    DB::table('chat_messages')
        ->whereNull('log_date')
        ->whereNotNull('daily_log_id')
        ->update([
            'domain' => 'food',
            'log_date' => DB::raw('(select date from daily_logs where daily_logs.id = chat_messages.daily_log_id)'),
        ]);

    $this->info("Backfill complete. Total rows processed: {$processed}");
})->purpose('Backfill measurements/activity/symptoms day tables from food daily logs');
