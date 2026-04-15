<?php

use App\Http\Controllers\ActivityChatController;
use App\Http\Controllers\ActivityDashboardController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebugResetDayController;
use App\Http\Controllers\MeasurementController;
use App\Http\Controllers\SymptomDailyLogController;
use App\Http\Controllers\SymptomsChatController;
use App\Http\Controllers\SymptomsDashboardController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard/{year?}/{month?}', DashboardController::class)->name('dashboard');
    Route::get('activity/{year?}/{month?}', ActivityDashboardController::class)->name('activity-dashboard');
    Route::get('symptoms/{year?}/{month?}', SymptomsDashboardController::class)->name('symptoms-dashboard');
    Route::patch('daily-logs/{daily_log}', [DailyLogController::class, 'update'])->name('daily-logs.update');
    Route::patch('measurements', [MeasurementController::class, 'upsert'])->name('measurements.upsert');
    Route::patch('symptom-daily-logs/{symptom_daily_log}', [SymptomDailyLogController::class, 'update'])->name('symptom-daily-logs.update');
    Route::post('chat', ChatController::class)->middleware('throttle:40,1')->name('chat.store');
    Route::post('activity-chat', ActivityChatController::class)->middleware('throttle:40,1')->name('activity-chat.store');
    Route::post('symptoms-chat', SymptomsChatController::class)->middleware('throttle:40,1')->name('symptoms-chat.store');
    Route::post('debug/reset-day', DebugResetDayController::class)->name('debug.reset-day');
});

require __DIR__.'/settings.php';
