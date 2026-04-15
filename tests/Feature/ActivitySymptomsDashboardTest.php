<?php

use App\Models\ActivityDailyLog;
use App\Models\AiWriteAudit;
use App\Models\DailyLog;
use App\Models\Measurement;
use App\Models\SymptomDailyLog;
use App\Models\User;
use App\Services\ActivityLogExtractor;
use App\Services\SymptomsLogExtractor;

test('authenticated user can visit activity and symptoms dashboards', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('activity-dashboard'))->assertOk();
    $this->get(route('symptoms-dashboard'))->assertOk();
});

test('weight updates sync through measurements table and daily logs', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $dailyLog = DailyLog::query()->create([
        'user_id' => $user->id,
        'date' => '2026-04-15',
        'weight_lbs' => null,
    ]);

    $this->patch(route('measurements.upsert'), [
        'log_date' => '2026-04-15',
        'weight_lbs' => '182.4',
    ])->assertRedirect();

    expect(Measurement::query()->where('user_id', $user->id)->whereDate('date', '2026-04-15')->first())
        ->not->toBeNull()
        ->weight_lbs->toBe('182.4');

    $dailyLog->refresh();
    expect((string) $dailyLog->weight_lbs)->toBe('182.4');
});

test('symptom update validates enums and pain bounds', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $symptomLog = SymptomDailyLog::query()->create([
        'user_id' => $user->id,
        'date' => '2026-04-15',
    ]);

    $this->patch(route('symptom-daily-logs.update', $symptomLog), [
        'trend' => 'invalid',
        'fatigue' => 'bad',
        'dizziness' => 'none',
        'max_pain' => 12,
    ])->assertSessionHasErrors(['trend', 'max_pain']);
});

test('activity chat writes domain data and audit rows', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->instance(ActivityLogExtractor::class, new class extends ActivityLogExtractor
    {
        public function extract(string $targetDateYmd, string $userContent, ?array $existingDay = null): array
        {
            return [
                'log_date' => $targetDateYmd,
                'total_sessions' => 2,
                'total_minutes' => 45,
                'calories_burned' => 430,
                'assistant_summary' => 'Logged activity totals.',
            ];
        }
    });

    $this->post(route('activity-chat.store'), [
        'log_date' => '2026-04-15',
        'message' => 'I did a run and strength.',
    ])->assertRedirect();

    $log = ActivityDailyLog::query()->where('user_id', $user->id)->whereDate('date', '2026-04-15')->first();
    expect($log)->not->toBeNull();
    expect($log?->total_sessions)->toBe(2);
    expect($log?->total_minutes)->toBe(45);
    expect($log?->calories_burned)->toBe(430);

    expect(AiWriteAudit::query()->where('user_id', $user->id)->where('domain', 'activity')->count())->toBe(1);
});

test('symptoms chat writes domain data and audit rows', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->instance(SymptomsLogExtractor::class, new class extends SymptomsLogExtractor
    {
        public function extract(string $targetDateYmd, string $userContent, ?array $existingDay = null): array
        {
            return [
                'log_date' => $targetDateYmd,
                'trend' => 'worse',
                'fatigue' => 'bad',
                'dizziness' => 'low',
                'max_pain' => 7,
                'assistant_summary' => 'Symptoms updated.',
            ];
        }
    });

    $this->post(route('symptoms-chat.store'), [
        'log_date' => '2026-04-15',
        'message' => 'Felt worse today.',
    ])->assertRedirect();

    $log = SymptomDailyLog::query()->where('user_id', $user->id)->whereDate('date', '2026-04-15')->first();
    expect($log)->not->toBeNull();
    expect($log?->trend)->toBe('worse');
    expect($log?->fatigue)->toBe('bad');
    expect($log?->dizziness)->toBe('low');
    expect($log?->max_pain)->toBe(7);

    expect(AiWriteAudit::query()->where('user_id', $user->id)->where('domain', 'symptoms')->count())->toBe(1);
});
