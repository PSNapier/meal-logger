<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebugResetDayController;
use App\Http\Controllers\FoodLibraryChatController;
use App\Http\Controllers\MyFoodsController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard/{year?}/{month?}', DashboardController::class)->name('dashboard');
    Route::patch('daily-logs/{daily_log}', [DailyLogController::class, 'update'])->name('daily-logs.update');
    Route::post('chat', ChatController::class)->middleware('throttle:40,1')->name('chat.store');
    Route::get('my-foods', [MyFoodsController::class, 'index'])->name('my-foods.index');
    Route::post('my-foods', [MyFoodsController::class, 'store'])->middleware('throttle:30,1')->name('my-foods.store');
    Route::patch('my-foods/{food_item}', [MyFoodsController::class, 'update'])->middleware('throttle:30,1')->name('my-foods.update');
    Route::delete('my-foods/{food_item}', [MyFoodsController::class, 'destroy'])->middleware('throttle:30,1')->name('my-foods.destroy');
    Route::post('my-foods/chat', FoodLibraryChatController::class)->middleware('throttle:20,1')->name('my-foods.chat.store');
    Route::post('debug/reset-day', DebugResetDayController::class)->name('debug.reset-day');
});

require __DIR__.'/settings.php';
