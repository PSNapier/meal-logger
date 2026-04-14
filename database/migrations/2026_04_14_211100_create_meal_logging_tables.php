<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('water_oz', 8, 2)->nullable();
            $table->decimal('fiber_g', 8, 2)->nullable();
            $table->unsignedInteger('calories')->nullable();
            $table->time('eating_window_start')->nullable();
            $table->time('eating_window_end')->nullable();
            $table->decimal('weight_lbs', 5, 1)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        Schema::create('meal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_log_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->unsignedInteger('calories')->default(0);
            $table->decimal('protein_g', 8, 2)->default(0);
            $table->decimal('carbs_g', 8, 2)->default(0);
            $table->decimal('fat_g', 8, 2)->default(0);
            $table->decimal('sugar_g', 8, 2)->default(0);
            $table->decimal('fiber_g', 8, 2)->default(0);
            $table->decimal('water_oz', 8, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_log_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 32);
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('meal_items');
        Schema::dropIfExists('daily_logs');
    }
};
