<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebugResetDayController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard/{year?}/{month?}', DashboardController::class)->name('dashboard');
    Route::patch('daily-logs/{daily_log}', [DailyLogController::class, 'update'])->name('daily-logs.update');
    Route::post('chat', ChatController::class)->middleware('throttle:40,1')->name('chat.store');
    Route::post('debug/reset-day', DebugResetDayController::class)->name('debug.reset-day');
});

require __DIR__.'/settings.php';
