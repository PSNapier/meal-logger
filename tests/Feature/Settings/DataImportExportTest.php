<?php

use App\Models\ActivityDailyLog;
use App\Models\DailyLog;
use App\Models\MealItem;
use App\Models\User;
use App\Services\UserDataPorter;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot access data settings', function (): void {
    $this->get(route('data.edit'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view data settings page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('data.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Data'));
});

test('export json contains only the authenticated users data', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    DailyLog::query()->create([
        'user_id' => $userA->id,
        'date' => '2026-04-10',
        'water_oz' => 32,
        'fiber_g' => null,
        'calories' => null,
        'eating_window_start' => null,
        'eating_window_end' => null,
        'weight_lbs' => null,
    ]);

    DailyLog::query()->create([
        'user_id' => $userB->id,
        'date' => '2026-04-11',
        'water_oz' => 99,
        'fiber_g' => null,
        'calories' => null,
        'eating_window_start' => null,
        'eating_window_end' => null,
        'weight_lbs' => null,
    ]);

    $response = $this->actingAs($userA)->get(route('data.export'));
    $response->assertOk();

    $payload = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($payload['version'])->toBe(1)
        ->and($payload['daily_logs'])->toHaveCount(1)
        ->and($payload['daily_logs'][0]['date'])->toBe('2026-04-10')
        ->and((float) $payload['daily_logs'][0]['water_oz'])->toBe(32.0);
});

test('import preview counts blank rows and conflicts', function (): void {
    $user = User::factory()->create();

    DailyLog::query()->create([
        'user_id' => $user->id,
        'date' => '2026-04-10',
        'water_oz' => 10,
        'fiber_g' => null,
        'calories' => null,
        'eating_window_start' => null,
        'eating_window_end' => null,
        'weight_lbs' => null,
    ]);

    $payload = [
        'version' => 1,
        'exported_at' => now()->toIso8601String(),
        'user' => ['id' => 999, 'email' => 'x@example.com'],
        'daily_logs' => [
            [
                'date' => '2026-04-09',
                'water_oz' => null,
                'fiber_g' => null,
                'calories' => null,
                'eating_window_start' => null,
                'eating_window_end' => null,
                'weight_lbs' => null,
                'meal_items' => [],
            ],
            [
                'date' => '2026-04-10',
                'water_oz' => 99,
                'fiber_g' => null,
                'calories' => null,
                'eating_window_start' => null,
                'eating_window_end' => null,
                'weight_lbs' => null,
                'meal_items' => [],
            ],
        ],
        'activity_daily_logs' => [],
        'symptom_daily_logs' => [],
        'measurements' => [],
    ];

    $file = UploadedFile::fake()->createWithContent('import.json', json_encode($payload));

    $this->actingAs($user)
        ->post(route('data.import.preview'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.daily_logs.blank', 1)
        ->assertJsonPath('summary.daily_logs.conflicting', 1)
        ->assertJsonPath('has_conflicts', true);
});

test('merge import fills null gaps when there is no conflict', function (): void {
    $user = User::factory()->create();

    DailyLog::query()->create([
        'user_id' => $user->id,
        'date' => '2026-04-10',
        'water_oz' => 10,
        'fiber_g' => null,
        'calories' => null,
        'eating_window_start' => null,
        'eating_window_end' => null,
        'weight_lbs' => null,
    ]);

    $payload = [
        'version' => 1,
        'exported_at' => now()->toIso8601String(),
        'user' => ['id' => $user->id, 'email' => $user->email],
        'daily_logs' => [
            [
                'date' => '2026-04-10',
                'water_oz' => null,
                'fiber_g' => 5,
                'calories' => null,
                'eating_window_start' => null,
                'eating_window_end' => null,
                'weight_lbs' => null,
                'meal_items' => [],
            ],
        ],
        'activity_daily_logs' => [],
        'symptom_daily_logs' => [],
        'measurements' => [],
    ];

    $file = UploadedFile::fake()->createWithContent('import.json', json_encode($payload));

    $this->actingAs($user)
        ->post(route('data.import.store'), [
            'file' => $file,
            'mode' => 'merge',
        ])
        ->assertOk();

    $log = DailyLog::query()->where('user_id', $user->id)->whereDate('date', '2026-04-10')->first();
    expect((float) $log->water_oz)->toBe(10.0)
        ->and((float) $log->fiber_g)->toBe(5.0);
});

test('overwrite import applies non null incoming fields on conflicts', function (): void {
    $user = User::factory()->create();

    DailyLog::query()->create([
        'user_id' => $user->id,
        'date' => '2026-04-10',
        'water_oz' => 10,
        'fiber_g' => 1,
        'calories' => null,
        'eating_window_start' => null,
        'eating_window_end' => null,
        'weight_lbs' => null,
    ]);

    $payload = [
        'version' => 1,
        'exported_at' => now()->toIso8601String(),
        'user' => ['id' => $user->id, 'email' => $user->email],
        'daily_logs' => [
            [
                'date' => '2026-04-10',
                'water_oz' => 50,
                'fiber_g' => null,
                'calories' => 200,
                'eating_window_start' => null,
                'eating_window_end' => null,
                'weight_lbs' => null,
                'meal_items' => [],
            ],
        ],
        'activity_daily_logs' => [],
        'symptom_daily_logs' => [],
        'measurements' => [],
    ];

    $file = UploadedFile::fake()->createWithContent('import.json', json_encode($payload));

    $this->actingAs($user)
        ->post(route('data.import.store'), [
            'file' => $file,
            'mode' => 'overwrite',
        ])
        ->assertOk();

    $log = DailyLog::query()->where('user_id', $user->id)->whereDate('date', '2026-04-10')->first();
    expect((float) $log->water_oz)->toBe(50.0)
        ->and((float) $log->fiber_g)->toBe(1.0)
        ->and($log->calories)->toBe(200);
});

test('blank daily log row does not clear existing data', function (): void {
    $user = User::factory()->create();

    DailyLog::query()->create([
        'user_id' => $user->id,
        'date' => '2026-04-10',
        'water_oz' => 10,
        'fiber_g' => null,
        'calories' => null,
        'eating_window_start' => null,
        'eating_window_end' => null,
        'weight_lbs' => null,
    ]);

    $payload = [
        'version' => 1,
        'exported_at' => now()->toIso8601String(),
        'user' => ['id' => $user->id, 'email' => $user->email],
        'daily_logs' => [
            [
                'date' => '2026-04-10',
                'water_oz' => null,
                'fiber_g' => null,
                'calories' => null,
                'eating_window_start' => null,
                'eating_window_end' => null,
                'weight_lbs' => null,
                'meal_items' => [],
            ],
        ],
        'activity_daily_logs' => [],
        'symptom_daily_logs' => [],
        'measurements' => [],
    ];

    $file = UploadedFile::fake()->createWithContent('import.json', json_encode($payload));

    $this->actingAs($user)
        ->post(route('data.import.store'), [
            'file' => $file,
            'mode' => 'overwrite',
        ])
        ->assertOk();

    $log = DailyLog::query()->where('user_id', $user->id)->whereDate('date', '2026-04-10')->first();
    expect((float) $log->water_oz)->toBe(10.0);
});

test('porter attaches imported rows to the authenticated user', function (): void {
    $user = User::factory()->create();
    $porter = app(UserDataPorter::class);

    $payload = [
        'version' => 1,
        'exported_at' => now()->toIso8601String(),
        'user' => ['id' => 99999, 'email' => 'other@example.com'],
        'daily_logs' => [],
        'activity_daily_logs' => [
            [
                'date' => '2026-05-01',
                'total_sessions' => 2,
                'total_minutes' => null,
                'calories_burned' => null,
            ],
        ],
        'symptom_daily_logs' => [],
        'measurements' => [],
    ];

    $porter->import($user, $payload, UserDataPorter::MODE_MERGE);

    $row = ActivityDailyLog::query()->where('user_id', $user->id)->whereDate('date', '2026-05-01')->first();
    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBe($user->id)
        ->and($row->total_sessions)->toBe(2);
});

test('overwrite replaces meal items when import has non empty meal_items', function (): void {
    $user = User::factory()->create();

    $log = DailyLog::query()->create([
        'user_id' => $user->id,
        'date' => '2026-04-10',
        'water_oz' => null,
        'fiber_g' => null,
        'calories' => null,
        'eating_window_start' => null,
        'eating_window_end' => null,
        'weight_lbs' => null,
    ]);

    MealItem::query()->create([
        'daily_log_id' => $log->id,
        'description' => 'Old',
        'calories' => 100,
        'protein_g' => 0,
        'carbs_g' => 0,
        'fat_g' => 0,
        'sugar_g' => 0,
        'fiber_g' => 0,
        'water_oz' => 0,
    ]);

    $payload = [
        'version' => 1,
        'exported_at' => now()->toIso8601String(),
        'user' => ['id' => $user->id, 'email' => $user->email],
        'daily_logs' => [
            [
                'date' => '2026-04-10',
                'water_oz' => null,
                'fiber_g' => null,
                'calories' => null,
                'eating_window_start' => null,
                'eating_window_end' => null,
                'weight_lbs' => null,
                'meal_items' => [
                    [
                        'description' => 'New',
                        'calories' => 200,
                        'protein_g' => 0,
                        'carbs_g' => 0,
                        'fat_g' => 0,
                        'sugar_g' => 0,
                        'fiber_g' => 0,
                        'water_oz' => 0,
                    ],
                ],
            ],
        ],
        'activity_daily_logs' => [],
        'symptom_daily_logs' => [],
        'measurements' => [],
    ];

    $porter = app(UserDataPorter::class);
    $porter->import($user, $payload, UserDataPorter::MODE_OVERWRITE);

    $items = MealItem::query()->where('daily_log_id', $log->id)->get();
    expect($items)->toHaveCount(1)
        ->and($items->first()->description)->toBe('New');
});
